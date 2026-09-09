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

        $central = AccountingScope::query()->updateOrCreate(
            ['code' => 'general_government'],
            ['name' => 'Ensemble des administrations publiques', 'description' => 'Périmètre consolidé des administrations publiques.', 'scope_type' => 'general_government'],
        );
        $centralAdmin = AccountingScope::query()->updateOrCreate(
            ['code' => 'central_government'],
            ['parent_id' => $central->id, 'name' => 'Administration publique centrale', 'scope_type' => 'central_government'],
        );
        AccountingScope::query()->updateOrCreate(['code' => 'state'], ['parent_id' => $centralAdmin->id, 'name' => 'État', 'scope_type' => 'state']);
        AccountingScope::query()->updateOrCreate(['code' => 'odac'], ['parent_id' => $centralAdmin->id, 'name' => 'Organismes divers d’administration centrale', 'scope_type' => 'odac']);
        AccountingScope::query()->updateOrCreate(['code' => 'local_government'], ['parent_id' => $central->id, 'name' => 'Administrations publiques locales', 'scope_type' => 'local_government']);
        AccountingScope::query()->updateOrCreate(['code' => 'social_security'], ['parent_id' => $central->id, 'name' => 'Administrations de sécurité sociale', 'scope_type' => 'social_security']);

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
            'insee_accounting' => ['Comptabilité nationale INSEE', 'national_accounts'],
            'cofog' => ['Classification fonctionnelle COFOG', 'cofog'],
        ] as $code => [$name, $dimension]) {
            Classification::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'description' => "Classification officielle par {$dimension}."],
            );
        }
    }
}
