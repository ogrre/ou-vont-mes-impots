<?php

use App\Http\Controllers\Api\V1\StateExpenditureController;
use App\Http\Controllers\Api\V1\StateRevenueController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('state-expenditure', StateExpenditureController::class)
        ->name('api.v1.state-expenditure.index');
    Route::get('state-revenue', StateRevenueController::class)
        ->name('api.v1.state-revenue.index');
});
