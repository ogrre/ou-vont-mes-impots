<?php

namespace App\Services\Imports;

use App\Enums\FinancialMeasure;
use App\Enums\FlowType;
use App\Enums\ImportStatus;
use App\Enums\ObservationStatus;
use App\Models\AccountingScope;
use App\Models\BudgetComponent;
use App\Models\Classification;
use App\Models\ClassificationItem;
use App\Models\DatasetFile;
use App\Models\FinancialObservation;
use App\Models\ImportBatch;
use App\Services\Imports\Contracts\DatasetImporter;
use App\Services\Imports\Exceptions\DuplicateImportException;
use App\Services\Imports\Exceptions\InvalidSourceDataException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class StateExpenditurePlrgImporter implements DatasetImporter
{
    private const COMPONENT_HEADERS = [
        'Budget général' => 'general_budget',
        'Budgets annexes' => 'annex_budget',
        "Comptes d'affectation spéciale" => 'special_allocation_account',
        'Comptes de concours financiers' => 'financial_assistance_account',
    ];

    public function __construct(
        private readonly DelimitedFileReader $reader,
        private readonly DecimalAmountNormalizer $normalizer,
    ) {}

    public function import(DatasetFile $datasetFile, string $path): ImportBatch
    {
        $datasetFile->loadMissing('dataset');
        $semantics = $this->validateDescriptor($datasetFile);

        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidSourceDataException("Fichier introuvable ou illisible : {$path}");
        }

        $checksum = hash_file('sha256', $path);

        if ($checksum === false) {
            throw new InvalidSourceDataException("Impossible de calculer le checksum de {$path}");
        }

        try {
            $batch = ImportBatch::query()->create([
                'dataset_id' => $datasetFile->dataset_id,
                'dataset_file_id' => $datasetFile->id,
                'filename' => basename($path),
                'checksum' => $checksum,
                'status' => ImportStatus::Running,
                'started_at' => now(),
                'metadata' => ['descriptor' => $datasetFile->slug],
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateImportException("Ce contenu a déjà été importé pour {$datasetFile->slug}.");
        }

        $rowsRead = 0;
        $rowsImported = 0;

        try {
            DB::transaction(function () use (
                $datasetFile, $path, $batch, $semantics, &$rowsRead, &$rowsImported,
            ): void {
                $scope = AccountingScope::query()->where('code', $semantics['accounting_scope'])->firstOrFail();
                $classification = Classification::query()
                    ->where('code', 'state_budget_'.$semantics['classification'])
                    ->firstOrFail();
                $components = BudgetComponent::query()->get()->keyBy('code');
                $headerMap = null;

                foreach ($this->reader->rows($path) as $rowNumber => $row) {
                    if ($headerMap === null) {
                        $headerMap = $this->validateHeaders($row);

                        continue;
                    }

                    $rowsRead++;
                    $label = trim((string) ($row[$headerMap['nom']] ?? ''));

                    if ($label === '') {
                        throw new InvalidSourceDataException("Libellé vide à la ligne {$rowNumber}.");
                    }

                    $item = ClassificationItem::query()->firstOrCreate(
                        ['classification_id' => $classification->id, 'official_label' => $label],
                        ['slug' => $this->uniqueSlug($classification, $label), 'metadata' => []],
                    );

                    foreach (self::COMPONENT_HEADERS as $header => $componentCode) {
                        $originalValue = trim((string) ($row[$headerMap[$header]] ?? ''));

                        if ($originalValue === '') {
                            continue;
                        }

                        $component = $components->get($componentCode);

                        if ($component === null) {
                            throw new InvalidSourceDataException("Composante budgétaire inconnue : {$header}");
                        }

                        FinancialObservation::query()->create([
                            'dataset_id' => $datasetFile->dataset_id,
                            'dataset_file_id' => $datasetFile->id,
                            'import_batch_id' => $batch->id,
                            'year' => $semantics['year'],
                            'accounting_scope_id' => $scope->id,
                            'budget_component_id' => $component->id,
                            'classification_item_id' => $item->id,
                            'status' => ObservationStatus::Executed,
                            'measure' => $semantics['measure'],
                            'flow_type' => FlowType::Expenditure,
                            'amount' => $this->normalizer->billionEurToEur($originalValue),
                            'currency' => 'EUR',
                            'source_row_number' => $rowNumber,
                            'source_identifier' => hash('sha256', $label.'|'.$componentCode),
                            'metadata' => [
                                'original_value' => $originalValue,
                                'original_unit' => 'billion_eur',
                                'source_component_label' => $header,
                            ],
                        ]);
                        $rowsImported++;
                    }
                }

                if ($headerMap === null) {
                    throw new InvalidSourceDataException('Le fichier CSV est vide.');
                }
            });

            $batch->update([
                'status' => ImportStatus::Completed,
                'completed_at' => now(),
                'rows_read' => $rowsRead,
                'rows_imported' => $rowsImported,
            ]);
        } catch (Throwable $exception) {
            $batch->update([
                'status' => ImportStatus::Failed,
                'completed_at' => now(),
                'rows_read' => $rowsRead,
                'rows_imported' => 0,
                'rows_rejected' => 1,
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $batch->fresh();
    }

    /** @return array{year: int, accounting_scope: string, classification: string, measure: FinancialMeasure} */
    private function validateDescriptor(DatasetFile $datasetFile): array
    {
        $metadata = $datasetFile->metadata ?? [];
        $classification = $metadata['classification'] ?? null;

        if (! in_array($classification, ['mission', 'ministry', 'nature'], true)) {
            throw new InvalidSourceDataException("Classification non prise en charge : {$classification}");
        }

        $measure = FinancialMeasure::tryFrom((string) ($metadata['measure'] ?? ''));

        if ($measure === null) {
            throw new InvalidSourceDataException('Mesure financière non prise en charge.');
        }

        if (($metadata['status'] ?? null) !== ObservationStatus::Executed->value
            || ($metadata['flow_type'] ?? null) !== FlowType::Expenditure->value
            || ($metadata['accounting_scope'] ?? null) !== 'french_state_budget'
            || ($metadata['original_unit'] ?? null) !== 'billion_eur') {
            throw new InvalidSourceDataException('Le descripteur PLRG ne porte pas les sémantiques attendues.');
        }

        return [
            'year' => (int) ($metadata['year'] ?? 0),
            'accounting_scope' => $metadata['accounting_scope'],
            'classification' => $classification,
            'measure' => $measure,
        ];
    }

    /** @param array<int, string|null> $headers
     * @return array<string, int>
     */
    private function validateHeaders(array $headers): array
    {
        $headers = array_map(
            fn ($header) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header) ?? ''),
            $headers,
        );
        $expected = ['nom', ...array_keys(self::COMPONENT_HEADERS)];
        $map = [];

        foreach ($expected as $header) {
            $index = array_search($header, $headers, true);

            if ($index === false) {
                throw new InvalidSourceDataException("En-tête CSV manquant ou inconnu : {$header}");
            }

            $map[$header] = $index;
        }

        return $map;
    }

    private function uniqueSlug(Classification $classification, string $label): string
    {
        $base = Str::slug($label) ?: 'item';

        if (! ClassificationItem::query()->where('classification_id', $classification->id)->where('slug', $base)->exists()) {
            return $base;
        }

        return $base.'-'.substr(hash('sha256', $label), 0, 8);
    }
}
