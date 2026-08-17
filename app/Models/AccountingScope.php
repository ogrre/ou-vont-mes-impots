<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingScope extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function observations(): HasMany
    {
        return $this->hasMany(FinancialObservation::class);
    }
}
