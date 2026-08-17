<?php

namespace Database\Seeders;

use App\Models\AccountingScope;
use App\Models\BudgetComponent;
use App\Models\Classification;
use Illuminate\Database\Seeder;

class FinancialReferenceSeeder extends Seeder
{
    public function run(): void
    {
        AccountingScope::query()->updateOrCreate(
            ['code' => 'french_state_budget'],
            [
                'name' => 'Budget de l’État français',
                'description' => 'Périmètre du budget de l’État uniquement ; il n’inclut pas l’ensemble des administrations publiques.',
            ],
        );

        foreach ([
            ['general_budget', 'Budget général', 'Budget général'],
            ['annex_budget', 'Budgets annexes', 'Budgets annexes'],
            ['special_allocation_account', 'Comptes d’affectation spéciale', "Comptes d'affectation spéciale"],
            ['financial_assistance_account', 'Comptes de concours financiers', 'Comptes de concours financiers'],
        ] as [$code, $name, $officialLabel]) {
            BudgetComponent::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'official_label' => $officialLabel],
            );
        }

        foreach ([
            'state_budget_mission' => ['Missions du budget de l’État', 'mission'],
            'state_budget_ministry' => ['Ministères du budget de l’État', 'ministry'],
            'state_budget_nature' => ['Natures de dépenses du budget de l’État', 'nature'],
            'state_budget_revenue' => ['Recettes du budget général de l’État', 'revenue'],
        ] as $code => [$name, $dimension]) {
            Classification::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'description' => "Classification officielle par {$dimension}."],
            );
        }
    }
}
