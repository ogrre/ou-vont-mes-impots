<?php

namespace App\Services\Imports;

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
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

class StateBudgetRevenueXlsxImporter implements DatasetImporter
{
    private const COLUMNS = [
        1 => ['year' => 2025, 'status' => ObservationStatus::InitialEstimate],
        2 => ['year' => 2025, 'status' => ObservationStatus::RevisedEstimate],
        3 => ['year' => 2026, 'status' => ObservationStatus::BudgetBill],
    ];

    private const AGGREGATES = [
        'Recettes fiscales brutes',
        'Recettes fiscales nettes (A)',
        'Recettes non fiscales (B)',
        'Prélèvements sur les recettes de l’État (C)',
        'Recettes totales nettes des prélèvements (A+B-C)',
        'Fonds de concours et attributions de produits (D)',
        'Recettes nettes totales du budget général (A+B-C+D)',
    ];

    public function __construct(private readonly DecimalAmountNormalizer $normalizer) {}

    public function import(DatasetFile $datasetFile, string $path): ImportBatch
    {
        $this->validateDescriptor($datasetFile);

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
                'metadata' => ['descriptor' => $datasetFile->slug, 'sheet' => 1],
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateImportException("Ce contenu a déjà été importé pour {$datasetFile->slug}.");
        }

        $rowsRead = 0;
        $rowsImported = 0;

        try {
            DB::transaction(function () use ($datasetFile, $path, $batch, &$rowsRead, &$rowsImported): void {
                $scope = AccountingScope::query()->where('code', 'french_state_budget')->firstOrFail();
                $component = BudgetComponent::query()->where('code', 'general_budget')->firstOrFail();
                $classification = Classification::query()->where('code', 'state_budget_revenue')->firstOrFail();
                $reader = new Reader;
                $headerValidated = false;
                $reader->open($path);

                try {
                    foreach ($reader->getSheetIterator() as $sheet) {
                        foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                            $values = array_slice($row->toArray(), 0, 4);

                            if ($rowNumber === 3) {
                                $this->validateHeaders($values);
                                $headerValidated = true;
                            }

                            if ($rowNumber < 4 || $rowNumber > 23) {
                                continue;
                            }

                            $rowsRead++;
                            $label = $this->normalizeLabel((string) ($values[0] ?? ''));

                            if ($label === '') {
                                throw new InvalidSourceDataException("Libellé de recette vide à la ligne {$rowNumber}.");
                            }

                            $item = ClassificationItem::query()->firstOrCreate(
                                ['classification_id' => $classification->id, 'official_label' => $label],
                                [
                                    'slug' => Str::slug($label),
                                    'metadata' => [
                                        'aggregation_role' => in_array($label, self::AGGREGATES, true) ? 'aggregate' : 'line_item',
                                        'must_not_be_summed_without_accounting_rules' => true,
                                    ],
                                ],
                            );

                            foreach (self::COLUMNS as $column => $semantics) {
                                $originalValue = $this->decimalSourceValue($values[$column] ?? null, $rowNumber, $column);

                                FinancialObservation::query()->create([
                                    'dataset_id' => $datasetFile->dataset_id,
                                    'dataset_file_id' => $datasetFile->id,
                                    'import_batch_id' => $batch->id,
                                    'year' => $semantics['year'],
                                    'accounting_scope_id' => $scope->id,
                                    'budget_component_id' => $component->id,
                                    'classification_item_id' => $item->id,
                                    'status' => $semantics['status'],
                                    'measure' => null,
                                    'flow_type' => FlowType::Revenue,
                                    'amount' => $this->normalizer->billionEurToEur($originalValue),
                                    'currency' => 'EUR',
                                    'source_row_number' => $rowNumber,
                                    'source_identifier' => hash('sha256', $label.'|'.$semantics['year'].'|'.$semantics['status']->value),
                                    'metadata' => [
                                        'original_value' => $originalValue,
                                        'original_unit' => 'billion_eur',
                                        'is_deduction' => str_starts_with($label, 'À déduire')
                                            || str_starts_with($label, 'Prélèvements sur'),
                                    ],
                                ]);
                                $rowsImported++;
                            }
                        }

                        break;
                    }

                    if (! $headerValidated || $rowsRead === 0) {
                        throw new InvalidSourceDataException('Le classeur de recettes est vide ou incomplet.');
                    }
                } finally {
                    $reader->close();
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
                'rows_rejected' => 1,
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $batch->fresh();
    }

    private function validateDescriptor(DatasetFile $file): void
    {
        $metadata = $file->metadata ?? [];

        if (($metadata['importer'] ?? null) !== 'state_budget_revenue_xlsx'
            || ($metadata['accounting_scope'] ?? null) !== 'french_state_budget'
            || ($metadata['classification'] ?? null) !== 'revenue'
            || ($metadata['flow_type'] ?? null) !== 'revenue'
            || ($metadata['original_unit'] ?? null) !== 'billion_eur') {
            throw new InvalidSourceDataException('Descripteur de recettes non pris en charge.');
        }
    }

    /** @param array<int, mixed> $headers */
    private function validateHeaders(array $headers): void
    {
        $expected = [
            'Recette',
            'Évaluations 2025 initiales',
            'Évaluations 2025 révisées',
            'Projet de loi de finances 2026',
        ];

        if ($headers !== $expected) {
            throw new InvalidSourceDataException('Les en-têtes du classeur de recettes ne correspondent pas au format attendu.');
        }
    }

    private function normalizeLabel(string $label): string
    {
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $label) ?? $label);
    }

    private function decimalSourceValue(mixed $value, int $row, int $column): string
    {
        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidSourceDataException("Montant de recette invalide à la ligne {$row}, colonne {$column}.");
        }

        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.') ?: '0';
    }
}
