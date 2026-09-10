<?php

namespace App\Services\Imports;

use App\Enums\AccountingBasis;
use App\Enums\FlowType;
use App\Enums\ImportStatus;
use App\Enums\MeasurementType;
use App\Enums\ObservationStatus;
use App\Models\AccountingScope;
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

/** Imports the presentation INSEE annual national-accounts tables (T_3201–T_3217). */
class InseePublicAccountsXlsxImporter implements DatasetImporter
{
    public function import(DatasetFile $datasetFile, string $path): ImportBatch
    {
        $metadata = $datasetFile->metadata ?? [];
        if (($metadata['importer'] ?? null) !== 'insee_public_accounts_xlsx') {
            throw new InvalidSourceDataException('Descripteur INSEE non pris en charge.');
        }
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
            DB::transaction(function () use ($datasetFile, $path, $batch, $metadata, &$rowsRead, &$rowsImported): void {
                $scope = AccountingScope::query()->where('code', $metadata['accounting_scope'])->firstOrFail();
                $classification = Classification::query()->where('code', 'insee_accounting')->firstOrFail();
                $reader = new Reader;
                $reader->open($path);
                try {
                    foreach ($reader->getSheetIterator() as $sheet) {
                        $years = null;
                        $section = $metadata['default_section'] ?? 'expenditure';
                        foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                            $values = array_map(static fn (mixed $value): string => trim((string) $value), $row->toArray());
                            if ($years === null) {
                                $years = $this->yearsFrom($values);
                                if ($years !== null) {
                                    continue;
                                }

                                continue;
                            }
                            $label = $this->firstText($values);
                            if ($label === '') {
                                continue;
                            }
                            if ($this->isStructuralLabel($label)) {
                                $section = $this->sectionFrom($label, $section);

                                continue;
                            }
                            $amounts = array_values(array_intersect_key($values, $years));
                            if (! $this->hasNumericValue($amounts)) {
                                continue;
                            }
                            $rowsRead++;
                            $semantics = $this->semantics($label, $section);
                            $item = ClassificationItem::query()->firstOrCreate(
                                ['classification_id' => $classification->id, 'official_label' => $label],
                                ['slug' => $this->uniqueSlug($classification, $label), 'metadata' => ['source_table' => $datasetFile->slug]],
                            );
                            foreach ($years as $column => $year) {
                                $raw = $values[$column] ?? '';
                                if ($raw === '' || ! is_numeric(str_replace(',', '.', $raw))) {
                                    continue;
                                }
                                $amount = number_format((float) str_replace(',', '.', $raw) * 1_000_000_000, 2, '.', '');
                                FinancialObservation::query()->create([
                                    'dataset_id' => $datasetFile->dataset_id,
                                    'dataset_file_id' => $datasetFile->id,
                                    'import_batch_id' => $batch->id,
                                    'year' => $year,
                                    'accounting_scope_id' => $scope->id,
                                    'institution_scope_id' => $scope->id,
                                    'classification_item_id' => $item->id,
                                    'category_id' => $item->id,
                                    'status' => ObservationStatus::Executed,
                                    'measurement_type' => $semantics['measurement_type'],
                                    'accounting_basis' => AccountingBasis::NationalAccounts,
                                    'is_consolidated' => $metadata['is_consolidated'] ?? false,
                                    'flow_type' => $semantics['flow_type'],
                                    'amount' => $amount,
                                    'currency' => 'EUR',
                                    'source_row_number' => $rowNumber,
                                    'source_identifier' => hash('sha256', $datasetFile->slug.'|'.$rowNumber.'|'.$year.'|'.$label.'|'.$column.'|'.$semantics['section']),
                                    'metadata' => [
                                        'original_value' => $raw,
                                        'original_unit' => 'billion_eur',
                                        'source_sheet' => $sheet->getName(),
                                        'section' => $semantics['section'],
                                    ],
                                ]);
                                $rowsImported++;
                            }
                        }
                    }
                } finally {
                    $reader->close();
                }
            });
            $batch->update(['status' => ImportStatus::Completed, 'completed_at' => now(), 'rows_read' => $rowsRead, 'rows_imported' => $rowsImported]);
        } catch (Throwable $exception) {
            $batch->update(['status' => ImportStatus::Failed, 'completed_at' => now(), 'rows_read' => $rowsRead, 'rows_rejected' => 1, 'error_message' => $exception->getMessage()]);
            throw $exception;
        }

        return $batch->fresh();
    }

    /**
     * @param  array<int,string>  $values
     * @return array<int,int>|null Column index to year.
     */
    private function yearsFrom(array $values): ?array
    {
        $years = [];
        foreach ($values as $index => $value) {
            if (preg_match('/^(19|20)\d{2}$/', $value)) {
                $years[$index] = (int) $value;
            }
        }

        return count($years) >= 2 ? $years : null;
    }

    /** @param array<int,string> $values */
    private function firstText(array $values): string
    {
        foreach ($values as $value) {
            if ($value !== '' && ! is_numeric(str_replace(',', '.', $value))) {
                return preg_replace('/\s+/u', ' ', $value) ?? $value;
            }
        }

        return '';
    }

    /** @param array<int,string> $values */
    private function hasNumericValue(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== '' && is_numeric(str_replace(',', '.', $value))) {
                return true;
            }
        }

        return false;
    }

    private function isStructuralLabel(string $label): bool
    {
        return in_array(mb_strtoupper($label), ['DEPENSES', 'DÉPENSES', 'RECETTES', 'SOLDES'], true) || str_starts_with($label, 'Source :');
    }

    private function sectionFrom(string $label, string $current): string
    {
        return match (mb_strtoupper($label)) {
            'DEPENSES', 'DÉPENSES' => 'expenditure',
            'RECETTES' => 'revenue',
            'SOLDES' => 'balance',
            default => $current,
        };
    }

    /** @return array{flow_type: FlowType, measurement_type: MeasurementType, section: string} */
    private function semantics(string $label, string $section): array
    {
        $lower = mb_strtolower($label);
        if ($section === 'balance') {
            return ['flow_type' => FlowType::Expenditure, 'measurement_type' => MeasurementType::Deficit, 'section' => $section];
        }
        if (str_contains($lower, 'impôt') || str_contains($lower, 'taxe') || str_contains($lower, 'prélèvement')) {
            return ['flow_type' => FlowType::Revenue, 'measurement_type' => MeasurementType::Tax, 'section' => 'tax'];
        }
        if (str_contains($lower, 'cotisation')) {
            return ['flow_type' => FlowType::Revenue, 'measurement_type' => MeasurementType::SocialContribution, 'section' => 'social_contribution'];
        }
        if ($section === 'revenue') {
            return ['flow_type' => FlowType::Revenue, 'measurement_type' => MeasurementType::Revenue, 'section' => $section];
        }

        return ['flow_type' => FlowType::Expenditure, 'measurement_type' => MeasurementType::Expenditure, 'section' => $section];
    }

    private function uniqueSlug(Classification $classification, string $label): string
    {
        $base = Str::slug($label) ?: 'item';

        return ClassificationItem::query()->where('classification_id', $classification->id)->where('slug', $base)->exists()
            ? $base.'-'.substr(hash('sha256', $label), 0, 8) : $base;
    }
}
