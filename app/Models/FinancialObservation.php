<?php

namespace App\Models;

use App\Enums\AccountingBasis;
use App\Enums\AeCp;
use App\Enums\BudgetStage;
use App\Enums\FinancialMeasure;
use App\Enums\FlowType;
use App\Enums\MeasurementType;
use App\Enums\ObservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dataset_id
 * @property int $dataset_file_id
 * @property int $import_batch_id
 * @property int $year
 * @property int $accounting_scope_id
 * @property int|null $institution_scope_id
 * @property int|null $budget_component_id
 * @property int $classification_item_id
 * @property int|null $category_id
 * @property ObservationStatus $status
 * @property MeasurementType|null $measurement_type
 * @property AccountingBasis|null $accounting_basis
 * @property BudgetStage|null $budget_stage
 * @property AeCp|null $ae_cp
 * @property bool|null $is_consolidated
 * @property FinancialMeasure|null $measure
 * @property FlowType $flow_type
 * @property numeric-string $amount
 * @property string $currency
 * @property int|null $source_row_number
 * @property string|null $source_identifier
 * @property array<string, mixed>|null $metadata
 * @property-read Dataset $dataset
 * @property-read DatasetFile $datasetFile
 * @property-read ImportBatch $importBatch
 * @property-read AccountingScope $accountingScope
 * @property-read BudgetComponent|null $budgetComponent
 * @property-read ClassificationItem $classificationItem
 */
class FinancialObservation extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ObservationStatus::class,
            'measurement_type' => MeasurementType::class,
            'accounting_basis' => AccountingBasis::class,
            'budget_stage' => BudgetStage::class,
            'ae_cp' => AeCp::class,
            'is_consolidated' => 'boolean',
            'measure' => FinancialMeasure::class,
            'flow_type' => FlowType::class,
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /** @return BelongsTo<DatasetFile, $this> */
    public function datasetFile(): BelongsTo
    {
        return $this->belongsTo(DatasetFile::class);
    }

    /** @return BelongsTo<ImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /** @return BelongsTo<AccountingScope, $this> */
    public function accountingScope(): BelongsTo
    {
        return $this->belongsTo(AccountingScope::class);
    }

    /** @return BelongsTo<AccountingScope, $this> */
    public function institutionScope(): BelongsTo
    {
        return $this->belongsTo(AccountingScope::class, 'institution_scope_id');
    }

    /** @return BelongsTo<BudgetComponent, $this> */
    public function budgetComponent(): BelongsTo
    {
        return $this->belongsTo(BudgetComponent::class);
    }

    /** @return BelongsTo<ClassificationItem, $this> */
    public function classificationItem(): BelongsTo
    {
        return $this->belongsTo(ClassificationItem::class);
    }

    /** @return BelongsTo<ClassificationItem, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ClassificationItem::class, 'category_id');
    }
}
