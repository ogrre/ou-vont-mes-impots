<?php

namespace Tests\Feature;

use App\Models\AccountingScope;
use App\Models\BudgetComponent;
use App\Models\Classification;
use App\Models\ClassificationItem;
use App\Models\Dataset;
use App\Models\DatasetFile;
use App\Models\FinancialObservation;
use App\Models\ImportBatch;
use App\Models\Source;
use App\Services\Imports\StateExpenditurePlrgImporter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_complete_provenance_graph_and_reference_relations_are_navigable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $file = DatasetFile::where('slug', 'state-expenditure-2025-mission-cp')->firstOrFail();
        app(StateExpenditurePlrgImporter::class)->import($file, base_path('tests/Fixtures/plrg/valid.csv'));

        $observation = FinancialObservation::firstOrFail();
        $batch = ImportBatch::firstOrFail();
        $dataset = Dataset::where('slug', 'state-expenditure-execution-2025')->firstOrFail();
        $source = Source::where('slug', 'french-state-budget-plrg')->firstOrFail();
        $scope = AccountingScope::where('code', 'french_state_budget')->firstOrFail();
        $component = BudgetComponent::where('code', 'general_budget')->firstOrFail();
        $classification = Classification::where('code', 'state_budget_mission')->firstOrFail();
        $item = $observation->classificationItem;
        $child = ClassificationItem::create([
            'classification_id' => $classification->id,
            'parent_id' => $item->id,
            'official_label' => 'Sous-ligne de test',
            'slug' => 'sous-ligne-de-test',
        ]);

        $this->assertTrue($source->datasets->contains($dataset));
        $this->assertTrue($dataset->source->is($source));
        $this->assertTrue($dataset->files->contains($file));
        $this->assertTrue($dataset->importBatches->contains($batch));
        $this->assertTrue($dataset->observations->contains($observation));
        $this->assertTrue($file->dataset->is($dataset));
        $this->assertTrue($file->importBatches->contains($batch));
        $this->assertTrue($batch->dataset->is($dataset));
        $this->assertTrue($batch->datasetFile->is($file));
        $this->assertTrue($batch->observations->contains($observation));
        $this->assertTrue($scope->observations->contains($observation));
        $this->assertTrue($component->observations->contains($observation));
        $this->assertTrue($classification->items->contains($item));
        $this->assertTrue($item->classification->is($classification));
        $this->assertTrue($item->children->contains($child));
        $this->assertTrue($child->parent->is($item));
        $this->assertTrue($item->observations->contains($observation));
        $this->assertTrue($observation->dataset->is($dataset));
        $this->assertTrue($observation->datasetFile->is($file));
        $this->assertTrue($observation->importBatch->is($batch));
        $this->assertTrue($observation->accountingScope->is($scope));
        $this->assertTrue($observation->budgetComponent->is($component));
    }
}
