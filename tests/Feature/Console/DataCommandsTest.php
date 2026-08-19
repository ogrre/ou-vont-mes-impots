<?php

namespace Tests\Feature\Console;

use App\Models\DatasetFile;
use App\Services\Imports\StateExpenditurePlrgImporter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DataCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_import_command_imports_a_known_descriptor(): void
    {
        $this->artisan('dataset:import', [
            'descriptor' => 'state-expenditure-2025-mission-cp',
            'path' => base_path('tests/Fixtures/plrg/valid.csv'),
        ])->expectsOutputToContain('Import #')
            ->assertSuccessful();
    }

    public function test_the_import_command_dispatches_the_revenue_importer(): void
    {
        $this->artisan('dataset:import', [
            'descriptor' => 'state-general-budget-revenue-2025-2026',
            'path' => base_path('data/econ-fin-pub-recettes-budget.xlsx'),
        ])->expectsOutputToContain('60 observations')
            ->assertSuccessful();
    }

    public function test_the_import_command_rejects_unknown_and_duplicate_descriptors(): void
    {
        $this->artisan('dataset:import', ['descriptor' => 'unknown', 'path' => 'missing.csv'])
            ->expectsOutputToContain('Descripteur inconnu')
            ->assertFailed();

        $arguments = [
            'descriptor' => 'state-expenditure-2025-mission-cp',
            'path' => base_path('tests/Fixtures/plrg/valid.csv'),
        ];
        $this->artisan('dataset:import', $arguments)->assertSuccessful();
        $this->artisan('dataset:import', $arguments)
            ->expectsOutputToContain('déjà été importé')
            ->assertExitCode(2);
    }

    public function test_the_import_command_reports_missing_importers_and_import_errors(): void
    {
        $file = DatasetFile::where('slug', 'state-expenditure-2025-mission-cp')->firstOrFail();
        $file->update(['metadata' => [...$file->metadata, 'importer' => 'unknown']]);

        $this->artisan('dataset:import', ['descriptor' => $file->slug, 'path' => 'missing.csv'])
            ->expectsOutputToContain('Aucun importeur')
            ->assertFailed();

        $file->update(['metadata' => [...$file->metadata, 'importer' => 'state_expenditure_plrg']]);
        $this->artisan('dataset:import', ['descriptor' => $file->slug, 'path' => 'missing.csv'])
            ->expectsOutputToContain('introuvable ou illisible')
            ->assertFailed();
    }

    public function test_the_known_datasets_command_imports_only_new_file_contents(): void
    {
        $directory = storage_path('framework/testing/known-datasets');
        File::ensureDirectoryExists($directory);
        File::copy(
            base_path('tests/Fixtures/plrg/valid.csv'),
            $directory.'/depenses-par-mission-plrg-cp-2025.csv',
        );

        try {
            $this->artisan('dataset:import-known', ['path' => $directory])
                ->expectsOutputToContain('1 nouveau(x)')
                ->assertSuccessful();

            $this->artisan('dataset:import-known', ['path' => $directory])
                ->expectsOutputToContain('contenu déjà importé, ignoré')
                ->expectsOutputToContain('0 nouveau(x), 1 déjà présent(s)')
                ->assertSuccessful();

            $this->assertDatabaseCount('import_batches', 1);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_the_validation_command_handles_unsupported_empty_valid_and_invalid_data(): void
    {
        $this->artisan('data:validate', ['dataset' => 'unknown'])
            ->expectsOutputToContain('non pris en charge')
            ->assertExitCode(2);

        $this->artisan('data:validate', ['dataset' => 'state-expenditure-2025'])
            ->expectsOutputToContain('écart(s)')
            ->assertFailed();

        $this->importClassifications('equal.csv');
        $this->artisan('data:validate', ['dataset' => 'state-expenditure-2025'])
            ->expectsOutputToContain('se réconcilient exactement')
            ->assertSuccessful();
    }

    private function importClassifications(string $fixture): void
    {
        foreach (['mission', 'ministry', 'nature'] as $classification) {
            $file = DatasetFile::where('slug', "state-expenditure-2025-{$classification}-cp")->firstOrFail();
            app(StateExpenditurePlrgImporter::class)->import($file, base_path('tests/Fixtures/plrg/'.$fixture));
        }
    }
}
