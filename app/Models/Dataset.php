<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dataset extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'downloaded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(DatasetFile::class);
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(FinancialObservation::class);
    }

    public function isPublishable(): bool
    {
        $metadata = $this->metadata ?? [];

        return $this->source?->is_official === true
            && filled($this->source?->publisher)
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
