<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 */
class Classification extends Model
{
    protected $guarded = [];

    /** @return HasMany<ClassificationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ClassificationItem::class);
    }
}
