<?php

namespace Tests\Feature\Imports;

use App\Enums\FinancialMeasure;
use App\Enums\FlowType;
use App\Enums\ImportStatus;
use App\Enums\ObservationStatus;
use App\Models\BudgetComponent;
use App\Models\ClassificationItem;
use App\Models\DatasetFile;
use App\Models\FinancialObservation;
use App\Services\Imports\Exceptions\DuplicateImportException;
use App\Services\Imports\Exceptions\InvalidSourceDataException;
use App\Services\Imports\StateExpenditurePlrgImporter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateExpenditurePlrgImporterTest extends TestCase
{
    use RefreshDatabase;

    private StateExpenditurePlrgImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->importer = app(StateExpenditurePlrgImporter::class);
    }

    public function test_it_imports_mission_cp_with_the_required_semantics_and_provenance(): void
    {
        $batch = $this->import('state-expenditure-2025-mission-cp');
        $observation = FinancialObservation::query()->with(
            'accountingScope', 'classificationItem.classification', 'budgetComponent',
            'importBatch.datasetFile.dataset.source',
        )->firstOrFail();

        $this->assertSame(ImportStatus::Completed, $batch->status);
        $this->assertSame(2, $batch->rows_read);
        $this->assertSame(5, $batch->rows_imported);
        $this->assertSame(2025, $observation->year);
        $this->assertSame(ObservationStatus::Executed, $observation->status);
        $this->assertSame(FinancialMeasure::PaymentCredit, $observation->measure);
        $this->assertSame(FlowType::Expenditure, $observation->flow_type);
        $this->assertSame('french_state_budget', $observation->accountingScope->code);
        $this->assertSame('state_budget_mission', $observation->classificationItem->classification->code);
        $this->assertSame('EUR', $observation->currency);
        $this->assertSame('1250000000.00', $observation->amount);
        $this->assertSame(2, $observation->source_row_number);
        $this->assertSame('billion_eur', $observation->metadata['original_unit']);
        $this->assertNotNull($observation->importBatch->datasetFile->dataset->source);
    }

    public function test_it_imports_mission_ae(): void
    {
        $this->import('state-expenditure-2025-mission-ae');

        $this->assertSame(FinancialMeasure::CommitmentAuthorization, FinancialObservation::firstOrFail()->measure);
    }

    public function test_it_imports_ministry(): void
    {
        $this->import('state-expenditure-2025-ministry-cp');

        $this->assertSame('state_budget_ministry', FinancialObservation::firstOrFail()->classificationItem->classification->code);
    }

    public function test_it_imports_nature(): void
    {
        $this->import('state-expenditure-2025-nature-cp');

        $this->assertSame('state_budget_nature', FinancialObservation::firstOrFail()->classificationItem->classification->code);
    }

    public function test_it_rejects_a_duplicate_file_for_the_same_descriptor(): void
    {
        $this->import('state-expenditure-2025-mission-cp');

        $this->expectException(DuplicateImportException::class);
        $this->import('state-expenditure-2025-mission-cp');
    }

    public function test_it_rejects_an_invalid_header(): void
    {
        $this->expectException(InvalidSourceDataException::class);
        $this->import('state-expenditure-2025-mission-cp', 'invalid-header.csv');
    }

    public function test_it_rejects_an_invalid_amount_and_rolls_back_observations(): void
    {
        try {
            $this->import('state-expenditure-2025-mission-cp', 'invalid-amount.csv');
            $this->fail('L’import aurait dû échouer.');
        } catch (InvalidSourceDataException) {
            $this->assertDatabaseCount('financial_observations', 0);
            $this->assertDatabaseHas('import_batches', ['status' => 'failed', 'rows_rejected' => 1]);
        }
    }

    public function test_it_rejects_an_unknown_budget_component_mapping(): void
    {
        BudgetComponent::query()->where('code', 'general_budget')->delete();

        $this->expectException(InvalidSourceDataException::class);
        $this->import('state-expenditure-2025-mission-cp');
    }

    public function test_it_rejects_an_unsupported_measure(): void
    {
        $file = DatasetFile::query()->where('slug', 'state-expenditure-2025-mission-cp')->firstOrFail();
        $file->update(['metadata' => [...$file->metadata, 'measure' => 'unknown']]);

        $this->expectException(InvalidSourceDataException::class);
        $this->importer->import($file->fresh(), $this->fixture('valid.csv'));
    }

    public function test_it_rejects_an_unsupported_classification(): void
    {
        $file = DatasetFile::query()->where('slug', 'state-expenditure-2025-mission-cp')->firstOrFail();
        $file->update(['metadata' => [...$file->metadata, 'classification' => 'editorial_category']]);

        $this->expectException(InvalidSourceDataException::class);
        $this->importer->import($file->fresh(), $this->fixture('valid.csv'));
    }

    public function test_it_rejects_inconsistent_plrg_semantics(): void
    {
        foreach (['status', 'flow_type', 'accounting_scope', 'original_unit'] as $field) {
            $file = DatasetFile::query()->where('slug', 'state-expenditure-2025-mission-cp')->firstOrFail();
            $metadata = $file->metadata;
            $original = $metadata[$field];
            $metadata[$field] = 'incorrect';
            $file->update(['metadata' => $metadata]);

            try {
                $this->importer->import($file->fresh(), $this->fixture('valid.csv'));
                $this->fail("La sémantique {$field} aurait dû être rejetée.");
            } catch (InvalidSourceDataException) {
                $this->assertDatabaseCount('financial_observations', 0);
            }

            $metadata[$field] = $original;
            $file->update(['metadata' => $metadata]);
        }
    }

    public function test_it_rejects_missing_empty_and_unlabelled_files(): void
    {
        $file = DatasetFile::where('slug', 'state-expenditure-2025-mission-cp')->firstOrFail();

        foreach (['missing.csv', 'empty.csv', 'empty-label.csv'] as $fixture) {
            try {
                $this->importer->import($file, $this->fixture($fixture));
                $this->fail("Le fichier {$fixture} aurait dû être rejeté.");
            } catch (InvalidSourceDataException) {
                $this->assertDatabaseCount('financial_observations', 0);
            }
        }
    }

    public function test_it_creates_deterministic_distinct_slugs_when_labels_collide(): void
    {
        $this->import('state-expenditure-2025-mission-cp', 'colliding-slugs.csv');
        $slugs = ClassificationItem::orderBy('id')->pluck('slug')->all();

        $this->assertSame('a-b', $slugs[0]);
        $this->assertMatchesRegularExpression('/^a-b-[a-f0-9]{8}$/', $slugs[1]);
    }

    private function import(string $slug, string $fixture = 'valid.csv')
    {
        $file = DatasetFile::query()->where('slug', $slug)->firstOrFail();

        return $this->importer->import($file, $this->fixture($fixture));
    }

    private function fixture(string $filename): string
    {
        return base_path('tests/Fixtures/plrg/'.$filename);
    }
}
