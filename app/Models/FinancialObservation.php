<?php

namespace App\Models;

use App\Enums\FinancialMeasure;
use App\Enums\FlowType;
use App\Enums\ObservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialObservation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ObservationStatus::class,
            'measure' => FinancialMeasure::class,
            'flow_type' => FlowType::class,
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    public function datasetFile(): BelongsTo
    {
        return $this->belongsTo(DatasetFile::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function accountingScope(): BelongsTo
    {
        return $this->belongsTo(AccountingScope::class);
    }

    public function budgetComponent(): BelongsTo
    {
        return $this->belongsTo(BudgetComponent::class);
    }

    public function classificationItem(): BelongsTo
    {
        return $this->belongsTo(ClassificationItem::class);
    }
}
