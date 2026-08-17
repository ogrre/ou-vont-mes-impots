<?php

namespace Tests\Feature\Imports;

use App\Models\DatasetFile;
use App\Services\Imports\StateExpenditurePlrgImporter;
use App\Services\Validation\StateExpenditureReconciliation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateExpenditureReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_equal_classifications_reconcile(): void
    {
        $this->importThreeClassifications('equal.csv');

        $result = app(StateExpenditureReconciliation::class)->validate();

        $this->assertTrue($result['valid']);
        $this->assertCount(0, $result['discrepancies']);
    }

    public function test_a_difference_is_reported(): void
    {
        $importer = app(StateExpenditurePlrgImporter::class);

        foreach (['mission', 'ministry'] as $classification) {
            $file = DatasetFile::where('slug', "state-expenditure-2025-{$classification}-cp")->firstOrFail();
            $importer->import($file, base_path('tests/Fixtures/plrg/equal.csv'));
        }

        $file = DatasetFile::where('slug', 'state-expenditure-2025-nature-cp')->firstOrFail();
        $importer->import($file, base_path('tests/Fixtures/plrg/different.csv'));
        $result = app(StateExpenditureReconciliation::class)->validate();

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['discrepancies']);
    }

    private function importThreeClassifications(string $fixture): void
    {
        $importer = app(StateExpenditurePlrgImporter::class);

        foreach (['mission', 'ministry', 'nature'] as $classification) {
            $file = DatasetFile::where('slug', "state-expenditure-2025-{$classification}-cp")->firstOrFail();
            $importer->import($file, base_path('tests/Fixtures/plrg/'.$fixture));
        }
    }
}
