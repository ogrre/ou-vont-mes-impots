<?php

use App\Http\Controllers\Api\V1\PublicFinanceController;
use App\Http\Controllers\Api\V1\StateExpenditureController;
use App\Http\Controllers\Api\V1\StateRevenueController;
use App\Http\Controllers\Api\V1\VersionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('version', VersionController::class)
        ->name('api.v1.version');
    Route::get('state-expenditure', StateExpenditureController::class)
        ->name('api.v1.state-expenditure.index');
    Route::get('state-revenue', StateRevenueController::class)
        ->name('api.v1.state-revenue.index');
    Route::get('years', [PublicFinanceController::class, 'years'])->name('api.v1.years');
    Route::get('sources', [PublicFinanceController::class, 'sources'])->name('api.v1.sources');
    Route::get('overview/{year}', [PublicFinanceController::class, 'overview'])->whereNumber('year')->name('api.v1.overview');
    Route::get('home/{year}', [PublicFinanceController::class, 'home'])->whereNumber('year')->name('api.v1.home');
    Route::get('budget-state/{year}/missions', [PublicFinanceController::class, 'budgetStateMissions'])->whereNumber('year')->name('api.v1.budget-state.missions');
    Route::get('budget-state/{year}/missions/{mission}', [PublicFinanceController::class, 'budgetStateMission'])->whereNumber('year')->name('api.v1.budget-state.mission');
    Route::get('budget-state/{year}/programmes/{programme}', [PublicFinanceController::class, 'budgetStateProgramme'])->whereNumber('year')->name('api.v1.budget-state.programme');
    Route::get('budget-state/{year}/programmes/{programme}/actions', [PublicFinanceController::class, 'budgetStateProgrammeActions'])->whereNumber('year')->name('api.v1.budget-state.programme.actions');
    Route::get('budget-state/{year}/actions/{action}', [PublicFinanceController::class, 'budgetStateAction'])->whereNumber('year')->name('api.v1.budget-state.action');
    Route::get('budget-state/{year}/distribution', [PublicFinanceController::class, 'budgetStateDistribution'])->whereNumber('year')->name('api.v1.budget-state.distribution');
    Route::get('budget-state/{year}/missions/{mission}/distribution', [PublicFinanceController::class, 'budgetStateDistribution'])->whereNumber('year')->name('api.v1.budget-state.mission.distribution');
    Route::get('budget-state/{year}/programmes/{programme}/distribution', [PublicFinanceController::class, 'budgetStateDistribution'])->whereNumber('year')->name('api.v1.budget-state.programme.distribution');
    Route::get('categories/{classification}', [PublicFinanceController::class, 'categories'])->name('api.v1.categories');
    Route::get('categories/{classification}/{category}/children', [PublicFinanceController::class, 'children'])->name('api.v1.categories.children');
    Route::get('history', [PublicFinanceController::class, 'history'])->name('api.v1.history');
    Route::get('search', [PublicFinanceController::class, 'search'])->name('api.v1.search');
    Route::get('methodology', fn () => response()->json(['version' => 'v1', 'principle' => 'Les recettes et les dépenses sont exposées séparément ; aucune relation directe recettes → dépenses n’est fabriquée.', 'accounting_bases' => ['budgetary', 'national_accounts'], 'notes' => ['AE et CP sont distinctes.', 'Les périmètres et années ne sont jamais masqués.']]))->name('api.v1.methodology');
});
