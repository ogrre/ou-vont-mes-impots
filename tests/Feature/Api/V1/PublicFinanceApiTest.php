<?php

namespace Tests\Feature\Api\V1;

use App\Models\DatasetFile;
use App\Services\Imports\InseePublicAccountsXlsxImporter;
use App\Services\Imports\InseeCofogXlsxImporter;
use App\Services\Imports\StateBudgetRevenueCsvImporter;
use App\Services\Api\PublicFinanceQuery;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFinanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_imported_years_sources_categories_and_history(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(InseePublicAccountsXlsxImporter::class)->import(
            DatasetFile::query()->where('slug', 'insee-t-3203')->firstOrFail(),
            base_path('data/series-historiques/insee/T_3203_fr.xlsx'),
        );

        $this->getJson('/api/v1/years')->assertOk()->assertJsonPath('years.0', 1978)->assertJsonPath('years.47', 2025);
        $this->getJson('/api/v1/sources')->assertOk()->assertJsonFragment(['code' => 'insee-t-3203']);
        $this->getJson('/api/v1/categories/insee_accounting')->assertOk()->assertJsonPath('classification', 'insee_accounting');
        $this->getJson('/api/v1/history?metric=expenditure&from=1978&to=1979')->assertOk()->assertJsonPath('items.0.year', 1978);
    }

    public function test_it_exposes_the_budget_state_hierarchy_routes_with_quality_metadata(): void
    {
        $payload = ['year' => 2024, 'items' => [['code' => '01', 'label' => 'Mission test', 'year' => 2024, 'ae' => ['lfi' => '100.00', 'execution' => '90.00'], 'cp' => ['lfi' => '100.00', 'execution' => '90.00'], 'quality' => ['status' => 'review_required', 'reason' => 'Contrôle à revoir', 'source' => 'Budget.gouv', 'source_page' => 12], 'provenance' => ['source_url' => 'https://www.budget.gouv.fr', 'dataset' => 'state-budget-rap-2024']]], 'coverage' => ['programmes_total' => 183, 'parsed' => 178, 'validated' => 139, 'review_required' => 39, 'not_importable' => 5]];
        $mock = $this->mock(PublicFinanceQuery::class);
        $mock->shouldReceive('budgetStateMissions')->with(2024)->andReturn($payload);

        $this->getJson('/api/v1/budget-state/2024/missions')->assertOk()->assertJsonPath('coverage.parsed', 178)->assertJsonPath('items.0.quality.status', 'review_required')->assertJsonPath('items.0.provenance.dataset', 'state-budget-rap-2024');
    }

    public function test_it_exposes_distribution_defaults_and_denominator(): void
    {
        $mock = $this->mock(PublicFinanceQuery::class);
        $mock->shouldReceive('budgetStateDistribution')->with(2024, null, null, 'payment_credit', 'executed', 'per_100')->andReturn(['year' => 2024, 'measurement' => 'payment_credit', 'stage' => 'executed', 'denominator' => '100.00', 'items' => [['code' => '01', 'amount' => '100.00', 'percent' => '100.00', 'per_100' => '100.00', 'quality_status' => 'validated']], 'quality' => ['coverage_percent' => '100.00']]);

        $this->getJson('/api/v1/budget-state/2024/distribution')->assertOk()->assertJsonPath('measurement', 'payment_credit')->assertJsonPath('stage', 'executed')->assertJsonPath('denominator', '100.00')->assertJsonPath('items.0.per_100', '100.00');
    }

    public function test_it_exposes_global_search_filters_and_canonical_identifiers(): void
    {
        $payload = ['query' => 'enseignement', 'year' => 2024, 'items' => [['type' => 'mission', 'code' => 'EDU', 'label' => 'Enseignement scolaire', 'year' => 2024, 'scope' => 'state_budget_programme_action', 'classification' => 'state_budget_programme_action', 'parent' => null, 'breadcrumb' => [['type' => 'mission', 'code' => 'EDU', 'label' => 'Enseignement scolaire']], 'amount' => '100.00', 'quality_status' => 'validated']]];
        $mock = $this->mock(PublicFinanceQuery::class);
        $mock->shouldReceive('search')->with('enseignement', 2024, null, null, 20)->andReturn($payload);

        $this->getJson('/api/v1/search?q=enseignement&year=2024')
            ->assertOk()
            ->assertJsonPath('items.0.type', 'mission')
            ->assertJsonPath('items.0.code', 'EDU')
            ->assertJsonMissingPath('items.0.route');
    }

    public function test_global_search_validates_and_caps_limit(): void
    {
        $this->getJson('/api/v1/search?q=a&limit=51')->assertUnprocessable()->assertJsonValidationErrors(['q', 'limit']);
    }

    public function test_overview_keeps_national_accounts_and_state_budget_separate_and_reports_missing_2024_datasets(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(InseePublicAccountsXlsxImporter::class)->import(
            DatasetFile::query()->where('slug', 'insee-t-3201')->firstOrFail(),
            base_path('data/series-historiques/insee/T_3201_fr.xlsx'),
        );

        $response = $this->getJson('/api/v1/overview/2024')->assertOk();

        $response->assertJsonPath('public_finances.accounting_basis', 'national_accounts')
            ->assertJsonPath('public_finances.scope', 'general_government')
            ->assertJsonPath('public_finances.expenditure.amount', '1672589200000.00')
            ->assertJsonPath('state_budget.accounting_basis', 'budgetary')
            ->assertJsonPath('state_budget.scope', 'french_state_budget')
            ->assertJsonPath('functional_distribution.amount', null)
            ->assertJsonPath('functional_distribution.quality.status', 'not_importable')
            ->assertJsonPath('revenues.public_revenues.accounting_basis', 'national_accounts')
            ->assertJsonPath('revenues.state_budget_revenues.amount', null)
            ->assertJsonPath('revenues.state_budget_revenues.quality.status', 'not_importable')
            ->assertJsonPath('institutional_distribution.quality.status', 'not_importable');
    }

    public function test_overview_exposes_imported_cofog_and_budget_revenues_without_merging_accounting_bases(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(InseeCofogXlsxImporter::class)->import(DatasetFile::where('slug', 'insee-t-3301')->firstOrFail(), base_path('data/2024/insee/T_3301.xlsx'));
        app(StateBudgetRevenueCsvImporter::class)->import(DatasetFile::where('slug', 'state-budget-revenue-execution-2024')->firstOrFail(), base_path('data/2024/budget-etat/fiscalite/Annexe1-Etat_Recettes.csv'));

        $this->getJson('/api/v1/overview/2024')->assertOk()
            ->assertJsonPath('functional_distribution.accounting_basis', 'national_accounts')
            ->assertJsonPath('functional_distribution.items.0.percent', '10.83')
            ->assertJsonPath('revenues.public_revenues.accounting_basis', 'national_accounts')
            ->assertJsonPath('revenues.state_budget_revenues.accounting_basis', 'budgetary')
            ->assertJsonPath('revenues.state_budget_revenues.stage', 'execution')
            ->assertJsonPath('revenues.state_budget_revenues.quality.status', 'validated');
    }

    public function test_home_contract_exposes_stable_frontend_blocks_and_global_metadata(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(InseePublicAccountsXlsxImporter::class)->import(DatasetFile::where('slug', 'insee-t-3201')->firstOrFail(), base_path('data/series-historiques/insee/T_3201_fr.xlsx'));

        $this->getJson('/api/v1/home/2024')->assertOk()
            ->assertJsonStructure(['data_year', 'reference_year', 'generated_at', 'methodology_version', 'headline', 'public_spending', 'who_spends', 'what_for', 'state_budget', 'revenues'])
            ->assertJsonPath('data_year', 2024)
            ->assertJsonPath('reference_year', 2024)
            ->assertJsonStructure(['public_spending' => ['title', 'description', 'amount', 'unit', 'items', 'percentage', 'per_100', 'quality_status', 'quality', 'methodology', 'provenance'], 'revenues' => ['items', 'sub_blocks' => ['public_revenues', 'state_budget_revenues']]])
            ->assertJsonPath('revenues.items.0.percentage', null)
            ->assertJsonPath('revenues.items.1.per_100', null);
    }
}
