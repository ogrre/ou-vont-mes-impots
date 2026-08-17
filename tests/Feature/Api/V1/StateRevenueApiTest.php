<?php

namespace Tests\Feature\Api\V1;

use App\Models\DatasetFile;
use App\Services\Imports\StateBudgetRevenueXlsxImporter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateRevenueApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_it_returns_revised_2025_revenue_by_default_without_an_invalid_grand_total(): void
    {
        $this->importRevenue();

        $this->getJson('/api/v1/state-revenue')
            ->assertOk()
            ->assertJsonPath('period', 2025)
            ->assertJsonPath('scope.code', 'french_state_budget')
            ->assertJsonPath('scope.budget_component', 'general_budget')
            ->assertJsonPath('status', 'revised_estimate')
            ->assertJsonPath('flow_type', 'revenue')
            ->assertJsonPath('classification', 'revenue')
            ->assertJsonPath('currency', 'EUR')
            ->assertJsonMissingPath('total')
            ->assertJsonCount(20, 'items')
            ->assertJsonPath('items.0.label', 'Recettes fiscales brutes')
            ->assertJsonPath('items.0.amount', '495065000000.00')
            ->assertJsonPath('items.0.is_aggregate', true)
            ->assertJsonPath('items.11.label', 'À déduire : Remboursements et dégrèvements')
            ->assertJsonPath('items.11.is_deduction', true)
            ->assertJsonPath('source.file.descriptor', 'state-general-budget-revenue-2025-2026');
    }

    public function test_it_filters_initial_estimates_and_the_2026_budget_bill(): void
    {
        $this->importRevenue();

        $this->getJson('/api/v1/state-revenue?year=2025&status=initial_estimate')
            ->assertOk()
            ->assertJsonPath('items.0.amount', '493186000000.00');

        $this->getJson('/api/v1/state-revenue?year=2026&status=budget_bill')
            ->assertOk()
            ->assertJsonPath('items.0.amount', '513756000000.00');
    }

    public function test_it_validates_filters_returns_not_found_and_is_read_only(): void
    {
        $this->getJson('/api/v1/state-revenue?year=2200&status=executed')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year', 'status']);

        $this->getJson('/api/v1/state-revenue')
            ->assertNotFound()
            ->assertJsonPath('message', 'Aucune recette importée ne correspond à ces filtres.');

        $this->postJson('/api/v1/state-revenue')->assertMethodNotAllowed();
    }

    private function importRevenue(): void
    {
        $file = DatasetFile::where('slug', 'state-general-budget-revenue-2025-2026')->firstOrFail();
        app(StateBudgetRevenueXlsxImporter::class)->import(
            $file,
            base_path('data/econ-fin-pub-recettes-budget.xlsx'),
        );
    }
}
