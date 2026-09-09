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
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

class InseeCofogXlsxImporter implements DatasetImporter
{
    public function import(DatasetFile $datasetFile, string $path): ImportBatch
    {
        $metadata = $datasetFile->metadata ?? [];
        if (($metadata['importer'] ?? null) !== 'insee_cofog_xlsx') {
            throw new InvalidSourceDataException('Descripteur COFOG non pris en charge.');
        }
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidSourceDataException("Fichier introuvable ou illisible : {$path}");
        }
        $checksum = hash_file('sha256', $path);
        if ($checksum === false) {
            throw new InvalidSourceDataException("Impossible de calculer le checksum de {$path}");
        }
        try {
            $batch = ImportBatch::query()->create(['dataset_id' => $datasetFile->dataset_id, 'dataset_file_id' => $datasetFile->id, 'filename' => basename($path), 'checksum' => $checksum, 'status' => ImportStatus::Running, 'started_at' => now(), 'metadata' => ['descriptor' => $datasetFile->slug]]);
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateImportException("Ce contenu a déjà été importé pour {$datasetFile->slug}.");
        }
        $read = 0;
        $written = 0;
        try {
            DB::transaction(function () use ($datasetFile, $path, $batch, &$read, &$written): void {
                $scope = AccountingScope::query()->where('code', 'general_government')->firstOrFail();
                $classification = Classification::query()->firstOrCreate(['code' => 'cofog'], ['name' => 'COFOG', 'description' => 'Classification des fonctions des administrations publiques.']);
                $reader = new Reader;
                $reader->open($path);
                try {
                    foreach ($reader->getSheetIterator() as $sheet) {
                        if ($sheet->getName() === 'Métadonnées') {
                            continue;
                        }
                        $years = null;
                        foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                            $values = array_map(static fn (mixed $value): string => trim((string) $value), $row->toArray());
                            if ($years === null) {
                                foreach ($values as $index => $value) {
                                    if (preg_match('/^(19|20)\\d{2}$/', $value)) {
                                        $years[(int) $value] = $index;
                                    }
                                }
                                if ($years !== null && ! array_key_exists(2024, $years)) {
                                    continue;
                                }
                                if ($years === null) {
                                    continue;
                                }

                                continue;
                            }
                            if (($values[2] ?? '') === '' || ! preg_match('/^(?:_Z|GF\d{2,})$/', $values[2]) || ! array_key_exists(2024, $years)) {
                                continue;
                            }
                            $code = $values[2];
                            $label = $values[3] ?? '';
                            $raw = $values[$years[2024]] ?? '';
                            if ($label === '' || $raw === '' || ! is_numeric(str_replace(',', '.', $raw))) {
                                continue;
                            }
                            $read++;
                            $level = $code === '_Z' ? 0 : (strlen($code) === 4 ? 1 : 2);
                            $parent = $level === 2 ? ClassificationItem::query()->where('classification_id', $classification->id)->where('code', substr($code, 0, 4))->first() : null;
                            $item = ClassificationItem::query()->updateOrCreate(['classification_id' => $classification->id, 'code' => $code], ['official_label' => $label, 'slug' => Str::slug($code.'-'.$label), 'parent_id' => $parent?->id, 'metadata' => ['cofog_level' => $level, 'raw_label' => $label]]);
                            FinancialObservation::query()->create(['dataset_id' => $datasetFile->dataset_id, 'dataset_file_id' => $datasetFile->id, 'import_batch_id' => $batch->id, 'year' => 2024, 'accounting_scope_id' => $scope->id, 'institution_scope_id' => $scope->id, 'classification_item_id' => $item->id, 'category_id' => $item->id, 'status' => ObservationStatus::Executed, 'measurement_type' => MeasurementType::Expenditure, 'accounting_basis' => AccountingBasis::NationalAccounts, 'budget_stage' => BudgetStage::Execution, 'is_consolidated' => true, 'flow_type' => FlowType::Expenditure, 'amount' => number_format((float) str_replace(',', '.', $raw) * 1000000000, 2, '.', ''), 'currency' => 'EUR', 'source_row_number' => $rowNumber, 'source_identifier' => hash('sha256', $datasetFile->slug.'|'.$sheet->getName().'|'.$rowNumber.'|'.$values[0].'|'.$code.'|2024'), 'metadata' => ['raw_label' => $label, 'source_field' => '2024', 'original_value' => $raw, 'original_unit' => 'billion_eur', 'consolidation' => 'consolidated', 'cofog_level' => $level, 'institution_code' => $values[0]]]);
                            $written++;
                        }
                    }
                } finally {
                    $reader->close();
                }
            });
            $batch->update(['status' => ImportStatus::Completed, 'completed_at' => now(), 'rows_read' => $read, 'rows_imported' => $written]);
        } catch (Throwable $exception) {
            $batch->update(['status' => ImportStatus::Failed, 'completed_at' => now(), 'rows_read' => $read, 'rows_rejected' => 1, 'error_message' => $exception->getMessage()]);
            throw $exception;
        }

        return $batch->fresh();
    }
}
