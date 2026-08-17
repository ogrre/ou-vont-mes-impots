<?php

namespace Database\Seeders;

use App\Enums\FinancialMeasure;
use App\Models\Dataset;
use App\Models\DatasetFile;
use App\Models\Source;
use Illuminate\Database\Seeder;

class PlrgDatasetSeeder extends Seeder
{
    public function run(): void
    {
        $source = Source::query()->updateOrCreate(
            ['slug' => 'french-state-budget-plrg'],
            [
                'name' => 'Données budgétaires de l’État — PLRG 2025',
                'publisher' => null,
                'homepage_url' => 'https://www.budget.gouv.fr/budget-etat',
                'description' => 'Source institutionnelle à compléter avec les références officielles exactes.',
                'is_official' => true,
            ],
        );

        $dataset = Dataset::query()->updateOrCreate(
            ['slug' => 'state-expenditure-execution-2025'],
            [
                'source_id' => $source->id,
                'name' => 'Exécution 2025 des dépenses du budget de l’État',
                'description' => 'Six vues PLRG du même périmètre comptable, par mission, ministère et nature, en AE et CP.',
                'source_url' => 'https://www.budget.gouv.fr/budget-etat',
                'year' => 2025,
                'metadata' => [
                    'accounting_scope' => 'french_state_budget',
                    'reporting_period' => '2025',
                    'status' => 'executed',
                    'flow_type' => 'expenditure',
                    'unit' => 'billion_eur',
                    'publication_readiness' => 'blocked_missing_provenance',
                ],
            ],
        );

        $files = [
            ['mission', 'ae', 'depenses-par-mission-plrg-ae-2025.csv'],
            ['mission', 'cp', 'depenses-par-mission-plrg-cp-2025.csv'],
            ['ministry', 'ae', 'depenses-par-ministeres-plrg-ae-2025.csv'],
            ['ministry', 'cp', 'depenses-par-ministeres-plrg-cp-2025.csv'],
            ['nature', 'ae', 'depenses-par-nature-plrg-ae-2025.csv'],
            ['nature', 'cp', 'depenses-par-nature-plrg-cp-2025.csv'],
        ];

        foreach ($files as [$classification, $shortMeasure, $filename]) {
            $measure = $shortMeasure === 'ae'
                ? FinancialMeasure::CommitmentAuthorization
                : FinancialMeasure::PaymentCredit;

            DatasetFile::query()->updateOrCreate(
                ['slug' => "state-expenditure-2025-{$classification}-{$shortMeasure}"],
                [
                    'dataset_id' => $dataset->id,
                    'expected_filename' => $filename,
                    'metadata' => [
                        'year' => 2025,
                        'accounting_scope' => 'french_state_budget',
                        'status' => 'executed',
                        'flow_type' => 'expenditure',
                        'classification' => $classification,
                        'measure' => $measure->value,
                        'official_measure_label' => $measure->officialLabel(),
                        'original_unit' => 'billion_eur',
                        'importer' => 'state_expenditure_plrg',
                    ],
                ],
            );
        }
    }
}
