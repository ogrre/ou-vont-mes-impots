<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $dataset_id
 * @property int $dataset_file_id
 * @property string $filename
 * @property string $checksum
 * @property ImportStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property int $rows_read
 * @property int $rows_imported
 * @property int $rows_rejected
 * @property string|null $error_message
 * @property array<string, mixed>|null $metadata
 * @property-read Dataset $dataset
 * @property-read DatasetFile $datasetFile
 */
class ImportBatch extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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

    /** @return HasMany<FinancialObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(FinancialObservation::class);
    }
}
