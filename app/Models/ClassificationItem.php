<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $classification_id
 * @property int|null $parent_id
 * @property string|null $code
 * @property string $official_label
 * @property string $slug
 * @property string|null $description
 * @property array<string, mixed>|null $metadata
 * @property-read Classification $classification
 * @property-read ClassificationItem|null $parent
 */
class ClassificationItem extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** @return BelongsTo<Classification, $this> */
    public function classification(): BelongsTo
    {
        return $this->belongsTo(Classification::class);
    }

    /** @return BelongsTo<ClassificationItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<ClassificationItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<FinancialObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(FinancialObservation::class);
    }
}
