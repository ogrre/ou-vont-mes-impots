<?php

namespace Tests\Feature\Api\V1;

use App\Models\DatasetFile;
use App\Services\Imports\StateExpenditurePlrgImporter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateExpenditureApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_it_returns_mission_cp_by_default_with_explicit_context_and_provenance(): void
    {
        $this->import('state-expenditure-2025-mission-cp');

        $response = $this->getJson('/api/v1/state-expenditure');

        $response->assertOk()
            ->assertJsonPath('period', 2025)
            ->assertJsonPath('scope.code', 'french_state_budget')
            ->assertJsonPath('status', 'executed')
            ->assertJsonPath('flow_type', 'expenditure')
            ->assertJsonPath('measure.code', 'payment_credit')
            ->assertJsonPath('measure.official_label', 'CP')
            ->assertJsonPath('classification', 'mission')
            ->assertJsonPath('currency', 'EUR')
            ->assertJsonPath('total', '7000000000.00')
            ->assertJsonPath('percentage_denominator.amount', '7000000000.00')
            ->assertJsonPath('items.0.label', 'Éducation')
            ->assertJsonPath('items.0.amount', '4000000000.00')
            ->assertJsonPath('items.0.percentage', '57.14')
            ->assertJsonCount(4, 'items.0.components')
            ->assertJsonPath('items.1.label', 'Santé')
            ->assertJsonPath('items.1.percentage', '42.85')
            ->assertJsonPath('source.dataset.id', 'state-expenditure-execution-2025')
            ->assertJsonPath('source.dataset.source_url', 'https://www.budget.gouv.fr/budget-etat')
            ->assertJsonPath('source.dataset.publication_ready', false)
            ->assertJsonPath('source.publisher.official', true)
            ->assertJsonPath('source.file.descriptor', 'state-expenditure-2025-mission-cp')
            ->assertJsonPath('source.import.rows_read', 2);

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $response->json('source.file.checksum_sha256'),
        );
    }

    public function test_it_accepts_official_and_internal_measure_names_and_each_classification(): void
    {
        foreach (['mission', 'ministry', 'nature'] as $classification) {
            $this->import("state-expenditure-2025-{$classification}-ae");

            $this->getJson("/api/v1/state-expenditure?classification={$classification}&measure=commitment_authorization")
                ->assertOk()
                ->assertJsonPath('classification', $classification)
                ->assertJsonPath('measure.official_label', 'AE');
        }
    }

    public function test_it_validates_filters_returns_not_found_and_is_read_only(): void
    {
        $this->getJson('/api/v1/state-expenditure?year=1999&classification=editorial&measure=total')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year', 'classification', 'measure']);

        $this->getJson('/api/v1/state-expenditure?year=2025&classification=mission&measure=cp')
            ->assertNotFound()
            ->assertJsonPath('message', 'Aucune dépense importée ne correspond à ces filtres.');

        $this->postJson('/api/v1/state-expenditure')->assertMethodNotAllowed();
    }

    public function test_it_allows_cross_origin_frontend_reads(): void
    {
        $this->withHeaders([
            'Origin' => 'https://frontend.example',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/state-expenditure')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_zero_total_returns_no_percentage(): void
    {
        $path = base_path('tests/Fixtures/plrg/zero.csv');
        $this->import('state-expenditure-2025-mission-cp', $path);

        $this->getJson('/api/v1/state-expenditure')
            ->assertOk()
            ->assertJsonPath('total', '0.00')
            ->assertJsonPath('items.0.percentage', null);
    }

    private function import(string $slug, ?string $path = null): void
    {
        $file = DatasetFile::where('slug', $slug)->firstOrFail();
        app(StateExpenditurePlrgImporter::class)->import(
            $file,
            $path ?? base_path('tests/Fixtures/plrg/valid.csv'),
        );
    }
}
