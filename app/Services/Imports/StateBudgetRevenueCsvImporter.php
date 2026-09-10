<?php

namespace App\Services\Imports;

use App\Enums\AccountingBasis;
use App\Enums\BudgetStage;
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
use Throwable;

class StateBudgetRevenueCsvImporter implements DatasetImporter
{
    public function import(DatasetFile $datasetFile, string $path): ImportBatch
    {
        if (($datasetFile->metadata['importer'] ?? null) !== 'state_budget_revenue_csv' || ! is_file($path)) {
            throw new InvalidSourceDataException('Descripteur ou fichier de recettes CSV invalide.');
        }
        $checksum = hash_file('sha256', $path);
        try {
            $batch = ImportBatch::query()->create(['dataset_id' => $datasetFile->dataset_id, 'dataset_file_id' => $datasetFile->id, 'filename' => basename($path), 'checksum' => $checksum, 'status' => ImportStatus::Running, 'started_at' => now(), 'metadata' => ['descriptor' => $datasetFile->slug]]);
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateImportException("Ce contenu a déjà été importé pour {$datasetFile->slug}.");
        }
        $read = 0;
        $written = 0;
        try {
            DB::transaction(function () use ($datasetFile, $path, $batch, &$read, &$written): void {
                $scope = AccountingScope::query()->where('code', 'french_state_budget')->firstOrFail();
                $classification = Classification::query()->where('code', 'state_budget_revenue')->firstOrFail();
                $handle = fopen($path, 'rb');
                if ($handle === false) {
                    throw new InvalidSourceDataException('CSV de recettes illisible.');
                }
                try {
                    $headers = fgetcsv($handle);
                    if ($headers === false || count($headers) < 10) {
                        throw new InvalidSourceDataException('En-têtes de recettes CSV incomplets.');
                    }
                    while (($values = fgetcsv($handle)) !== false) {
                        if (count($values) < 10) {
                            continue;
                        }
                        $read++;
                        $level = (int) trim((string) $values[0]);
                        $category = $this->clean($values[1]);
                        $section = $this->clean($values[2]);
                        $line = $this->clean($values[4]);
                        $label = $line !== '' ? $line : ($section !== '' ? $section : $category);
                        if ($label === '') {
                            continue;
                        }
                        $pathParts = array_values(array_filter([$category, $section, $line], fn ($v) => $v !== ''));
                        $code = implode('.', $pathParts);
                        if (mb_strlen($code) > 180) {
                            $code = mb_substr($code, 0, 100).'-'.substr(hash('sha256', implode('|', $pathParts)), 0, 16);
                        }
                        $displayLabel = mb_strlen($label) > 160 ? mb_substr($label, 0, 160).'…' : $label;
                        $parent = null;
                        if ($level > 0) {
                            $parent = ClassificationItem::query()->where('classification_id', $classification->id)->where('metadata->csv_path', implode('|', array_slice($pathParts, 0, $level)))->first();
                        }
                        $slug = mb_substr(Str::slug($code.'-'.$label), 0, 100).'-'.substr(hash('sha256', $code.'|'.$label), 0, 16);
                        $item = ClassificationItem::query()->firstOrCreate(['classification_id' => $classification->id, 'code' => $code], ['official_label' => $displayLabel, 'slug' => $slug, 'parent_id' => $parent?->id, 'metadata' => ['csv_level' => $level, 'csv_path' => implode('|', $pathParts), 'raw_label' => $label]]);
                        foreach ([['year' => 2024, 'column' => 7, 'stage' => BudgetStage::InitialBudget, 'status' => ObservationStatus::InitialEstimate, 'field' => 'Total des prévisions'], ['year' => 2024, 'column' => 9, 'stage' => BudgetStage::Execution, 'status' => ObservationStatus::Executed, 'field' => 'Total des recettes**']] as $semantics) {
                            $raw = $this->clean($values[$semantics['column']] ?? '');
                            if ($raw === '') {
                                continue;
                            }
                            FinancialObservation::query()->create(['dataset_id' => $datasetFile->dataset_id, 'dataset_file_id' => $datasetFile->id, 'import_batch_id' => $batch->id, 'year' => $semantics['year'], 'accounting_scope_id' => $scope->id, 'institution_scope_id' => $scope->id, 'classification_item_id' => $item->id, 'category_id' => $item->id, 'status' => $semantics['status'], 'measurement_type' => MeasurementType::Revenue, 'accounting_basis' => AccountingBasis::Budgetary, 'budget_stage' => $semantics['stage'], 'is_consolidated' => false, 'flow_type' => FlowType::Revenue, 'amount' => $this->euro($raw), 'currency' => 'EUR', 'source_row_number' => $read + 1, 'source_identifier' => hash('sha256', $code.'|'.$semantics['field'].'|'.$read), 'metadata' => ['raw_label' => $label, 'source_field' => $semantics['field'], 'raw_value' => $raw, 'csv_level' => $level, 'csv_path' => implode('|', $pathParts)]]);
                            $written++;
                        }
                    }
                } finally {
                    fclose($handle);
                }
            });
            $batch->update(['status' => ImportStatus::Completed, 'completed_at' => now(), 'rows_read' => $read, 'rows_imported' => $written]);
        } catch (Throwable $exception) {
            $batch->update(['status' => ImportStatus::Failed, 'completed_at' => now(), 'rows_read' => $read, 'rows_rejected' => 1, 'error_message' => $exception->getMessage()]);
            throw $exception;
        }

        return $batch->fresh();
    }

    private function clean(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace(["\xC2\xA0", "\xEF\xBB\xBF"], ' ', (string) $value)) ?? (string) $value);
    }

    private function euro(string $value): string
    {
        return number_format((float) str_replace([',', ' '], ['.', ''], $value), 2, '.', '');
    }
}
