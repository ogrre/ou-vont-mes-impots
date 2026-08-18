<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $publisher
 * @property string|null $homepage_url
 * @property string|null $description
 * @property bool $is_official
 */
class Source extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_official' => 'boolean'];
    }

    /** @return HasMany<Dataset, $this> */
    public function datasets(): HasMany
    {
        return $this->hasMany(Dataset::class);
    }
}
