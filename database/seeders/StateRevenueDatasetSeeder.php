<?php

namespace Database\Seeders;

use App\Models\Dataset;
use App\Models\DatasetFile;
use App\Models\Source;
use Illuminate\Database\Seeder;

class StateRevenueDatasetSeeder extends Seeder
{
    public function run(): void
    {
        $source = Source::query()->updateOrCreate(
            ['slug' => 'ministry-economy-finance-plf-2026'],
            [
                'name' => 'Projet de loi de finances 2026',
                'publisher' => 'Ministère de l’Économie, des Finances et de la Souveraineté industrielle et numérique',
                'description' => 'Producteur indiqué dans le classeur ; URL officielle et conditions de réutilisation à compléter.',
                'is_official' => true,
            ],
        );

        $dataset = Dataset::query()->updateOrCreate(
            ['slug' => 'state-general-budget-revenue-plf-2026'],
            [
                'source_id' => $source->id,
                'name' => 'Recettes nettes du budget général — PLF 2026',
                'publication_title' => 'Projet de loi de finances 2026',
                'description' => 'Estimations initiales et révisées 2025, et projet de loi de finances 2026.',
                'metadata' => [
                    'accounting_scope' => 'french_state_budget',
                    'reporting_period' => '2025-2026',
                    'unit' => 'billion_eur',
                    'statuses' => ['initial_estimate', 'revised_estimate', 'budget_bill'],
                    'publication_readiness' => 'blocked_missing_provenance',
                ],
            ],
        );

        DatasetFile::query()->updateOrCreate(
            ['slug' => 'state-general-budget-revenue-2025-2026'],
            [
                'dataset_id' => $dataset->id,
                'expected_filename' => 'econ-fin-pub-recettes-budget.xlsx',
                'metadata' => [
                    'accounting_scope' => 'french_state_budget',
                    'classification' => 'revenue',
                    'flow_type' => 'revenue',
                    'original_unit' => 'billion_eur',
                    'importer' => 'state_budget_revenue_xlsx',
                ],
            ],
        );
    }
}
