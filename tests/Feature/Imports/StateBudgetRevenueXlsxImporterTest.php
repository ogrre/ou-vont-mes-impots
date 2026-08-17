<?php

namespace Tests\Feature\Imports;

use App\Enums\FlowType;
use App\Enums\ObservationStatus;
use App\Models\Dataset;
use App\Models\DatasetFile;
use App\Models\FinancialObservation;
use App\Services\Imports\Exceptions\DuplicateImportException;
use App\Services\Imports\Exceptions\InvalidSourceDataException;
use App\Services\Imports\StateBudgetRevenueXlsxImporter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class StateBudgetRevenueXlsxImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_each_workbook_column_with_its_own_status(): void
    {
        $this->seed(DatabaseSeeder::class);
        $file = DatasetFile::where('slug', 'state-general-budget-revenue-2025-2026')->firstOrFail();
        $batch = app(StateBudgetRevenueXlsxImporter::class)->import(
            $file,
            base_path('data/econ-fin-pub-recettes-budget.xlsx'),
        );

        $this->assertSame(20, $batch->rows_read);
        $this->assertSame(60, $batch->rows_imported);
        $this->assertDatabaseHas('financial_observations', [
            'year' => 2025,
            'status' => ObservationStatus::InitialEstimate->value,
            'flow_type' => FlowType::Revenue->value,
            'amount' => '493186000000.00',
        ]);
        $this->assertDatabaseHas('financial_observations', [
            'year' => 2025,
            'status' => ObservationStatus::RevisedEstimate->value,
            'amount' => '495065000000.00',
        ]);
        $this->assertDatabaseHas('financial_observations', [
            'year' => 2026,
            'status' => ObservationStatus::BudgetBill->value,
            'amount' => '513756000000.00',
        ]);
        $this->assertNull(FinancialObservation::query()->firstOrFail()->measure);

        $deduction = FinancialObservation::query()
            ->whereHas('classificationItem', fn ($query) => $query->where('official_label', 'À déduire : Remboursements et dégrèvements'))
            ->firstOrFail();
        $aggregate = FinancialObservation::query()
            ->whereHas('classificationItem', fn ($query) => $query->where('official_label', 'Recettes fiscales brutes'))
            ->firstOrFail();
        $lineItem = FinancialObservation::query()
            ->whereHas('classificationItem', fn ($query) => $query->where('official_label', 'Impôt sur le revenu'))
            ->firstOrFail();

        $this->assertTrue($deduction->metadata['is_deduction']);
        $this->assertSame('aggregate', $aggregate->classificationItem->metadata['aggregation_role']);
        $this->assertSame('line_item', $lineItem->classificationItem->metadata['aggregation_role']);
        $this->assertSame(4, $aggregate->source_row_number);
    }

    public function test_missing_provenance_keeps_the_dataset_unpublishable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $dataset = Dataset::where('slug', 'state-expenditure-execution-2025')->with('source')->firstOrFail();

        $this->assertFalse($dataset->isPublishable());
    }

    public function test_it_rejects_duplicate_missing_and_semantically_invalid_sources(): void
    {
        $this->seed(DatabaseSeeder::class);
        $file = DatasetFile::where('slug', 'state-general-budget-revenue-2025-2026')->firstOrFail();
        $importer = app(StateBudgetRevenueXlsxImporter::class);
        $path = base_path('data/econ-fin-pub-recettes-budget.xlsx');
        $importer->import($file, $path);

        try {
            $importer->import($file, $path);
            $this->fail('Le doublon aurait dû être rejeté.');
        } catch (DuplicateImportException) {
            $this->assertDatabaseCount('import_batches', 1);
        }

        $this->expectException(InvalidSourceDataException::class);
        $importer->import($file, base_path('data/missing.xlsx'));
    }

    public function test_it_rejects_an_unsupported_descriptor(): void
    {
        $this->seed(DatabaseSeeder::class);
        $file = DatasetFile::where('slug', 'state-general-budget-revenue-2025-2026')->firstOrFail();
        $file->update(['metadata' => [...$file->metadata, 'flow_type' => 'expenditure']]);

        $this->expectException(InvalidSourceDataException::class);
        app(StateBudgetRevenueXlsxImporter::class)->import($file->fresh(), base_path('data/econ-fin-pub-recettes-budget.xlsx'));
    }

    public function test_it_rejects_invalid_headers_amounts_and_incomplete_workbooks(): void
    {
        $this->seed(DatabaseSeeder::class);
        $file = DatasetFile::where('slug', 'state-general-budget-revenue-2025-2026')->firstOrFail();
        $importer = app(StateBudgetRevenueXlsxImporter::class);

        $cases = [
            [
                ['Titre'],
                ['Unité'],
                ['Mauvais en-tête', 'Initial', 'Révisé', 'PLF'],
            ],
            [
                ['Titre'],
                ['Unité'],
                ['Recette', 'Évaluations 2025 initiales', 'Évaluations 2025 révisées', 'Projet de loi de finances 2026'],
                ['Ligne', 'invalide', 2, 3],
            ],
            [
                ['Titre'],
                ['Unité'],
                ['Recette', 'Évaluations 2025 initiales', 'Évaluations 2025 révisées', 'Projet de loi de finances 2026'],
                ['', 1, 2, 3],
            ],
            [['Titre seulement']],
        ];

        foreach ($cases as $rows) {
            $path = $this->workbook($rows);

            try {
                $importer->import($file, $path);
                $this->fail('Le classeur invalide aurait dû être rejeté.');
            } catch (InvalidSourceDataException) {
                $this->assertDatabaseCount('financial_observations', 0);
            } finally {
                unlink($path);
            }
        }
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function workbook(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'revenue-test-');
        $writer = new Writer;
        $writer->openToFile($path);

        foreach ($rows as $values) {
            $writer->addRow(Row::fromValues($values));
        }

        $writer->close();

        return $path;
    }
}
