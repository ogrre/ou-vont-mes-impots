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

        $source2024 = Source::query()->updateOrCreate(['slug' => 'direction-budget-plrg-2024'], ['name' => 'PLRG 2024', 'publisher' => 'Direction du Budget', 'homepage_url' => 'https://www.budget.gouv.fr/documentation/documents-budgetaires/exercice-2024/plrg-2024', 'is_official' => true]);
        $dataset2024 = Dataset::query()->updateOrCreate(['slug' => 'state-budget-revenue-execution-2024'], ['source_id' => $source2024->id, 'name' => 'Recettes du budget de l’État — exécution 2024', 'description' => 'Hiérarchie des recettes exécutées et prévisionnelles du PLRG 2024.', 'accounting_system' => 'budgetary', 'scope' => 'state_budget', 'frequency' => 'annual', 'unit' => 'EUR', 'year' => 2024, 'metadata' => ['accounting_scope' => 'state_budget', 'reporting_period' => '2024', 'status' => 'executed', 'unit' => 'EUR']]);
        DatasetFile::query()->updateOrCreate(['slug' => 'state-budget-revenue-execution-2024'], ['dataset_id' => $dataset2024->id, 'expected_filename' => 'Annexe1-Etat_Recettes.csv', 'metadata' => ['importer' => 'state_budget_revenue_csv', 'accounting_scope' => 'french_state_budget', 'classification' => 'revenue', 'flow_type' => 'revenue']]);
    }
}
