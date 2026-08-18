<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $source_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $source_url
 * @property string|null $download_url
 * @property string|null $publication_title
 * @property Carbon|null $publication_date
 * @property Carbon|null $downloaded_at
 * @property string|null $license_name
 * @property string|null $license_url
 * @property int|null $year
 * @property array<string, mixed>|null $metadata
 * @property-read Source|null $source
 */
class Dataset extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'downloaded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /** @return HasMany<DatasetFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(DatasetFile::class);
    }

    /** @return HasMany<ImportBatch, $this> */
    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }

    /** @return HasMany<FinancialObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(FinancialObservation::class);
    }

    public function isPublishable(): bool
    {
        $metadata = $this->metadata;

        if (! is_array($metadata)) {
            return false;
        }

        return $this->source->is_official === true
            && filled($this->source->publisher)
            && filled($this->source_url)
            && filled($this->publication_title)
            && $this->downloaded_at !== null
            && filled($this->license_name)
            && $this->year !== null
            && filled($metadata['accounting_scope'] ?? null)
            && filled($metadata['reporting_period'] ?? null)
            && filled($metadata['unit'] ?? null)
            && filled($metadata['status'] ?? null);
    }
}
