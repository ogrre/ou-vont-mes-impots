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
class AccountingScope extends Model
{
    protected $guarded = [];

    /** @return HasMany<FinancialObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(FinancialObservation::class);
    }
}
