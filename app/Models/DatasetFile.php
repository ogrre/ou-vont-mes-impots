<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $dataset_id
 * @property string $slug
 * @property string|null $expected_filename
 * @property array<string, mixed> $metadata
 * @property-read Dataset $dataset
 */
class DatasetFile extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /** @return HasMany<ImportBatch, $this> */
    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }
}
