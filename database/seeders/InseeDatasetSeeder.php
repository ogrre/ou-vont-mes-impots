<?php

namespace Database\Seeders;

use App\Models\Dataset;
use App\Models\DatasetFile;
use App\Models\Source;
use Illuminate\Database\Seeder;

class InseeDatasetSeeder extends Seeder
{
    public function run(): void
    {
        $source = Source::query()->updateOrCreate(['slug' => 'insee'], [
            'name' => 'INSEE', 'publisher' => 'Institut national de la statistique et des études économiques',
            'homepage_url' => 'https://www.insee.fr', 'description' => 'Comptes nationaux annuels, base 2020.', 'is_official' => true,
        ]);
        $scopes = [
            1 => ['general_government', 1959, 2025], 2 => ['central_government', 1978, 2025], 3 => ['state', 1978, 2025], 4 => ['odac', 1978, 2025],
            5 => ['local_government', 1978, 2025], 6 => ['local_government', 2013, 2025], 7 => ['local_government', 2019, 2025], 8 => ['local_government', 2019, 2025],
            9 => ['local_government', 2019, 2025], 10 => ['local_government', 2019, 2025], 11 => ['local_government', 2013, 2025], 12 => ['social_security', 1978, 2025],
            13 => ['social_security', 2013, 2025], 14 => ['social_security', 2013, 2025], 15 => ['general_government', 1978, 2025], 16 => ['general_government', 1959, 2025], 17 => ['general_government', 1995, 2025],
        ];
        foreach ($scopes as $number => [$scope, $first, $last]) {
            $code = sprintf('insee-t-%04d', 3200 + $number);
            $dataset = Dataset::query()->updateOrCreate(['slug' => $code], [
                'source_id' => $source->id, 'name' => 'INSEE T_'.(3200 + $number),
                'description' => 'Tableau INSEE de comptabilité nationale.', 'accounting_system' => 'national_accounts', 'scope' => $scope,
                'frequency' => 'annual', 'unit' => 'billion_eur', 'first_year' => $first, 'last_year' => $last,
                'metadata' => ['accounting_scope' => $scope, 'reporting_period' => $first.'-'.$last, 'unit' => 'billion_eur', 'status' => 'executed'],
            ]);
            DatasetFile::query()->updateOrCreate(['slug' => $code], [
                'dataset_id' => $dataset->id, 'expected_filename' => 'T_'.(3200 + $number).'_fr.xlsx',
                'metadata' => ['importer' => 'insee_public_accounts_xlsx', 'accounting_scope' => $scope, 'is_consolidated' => in_array($number, [1, 5, 15, 16, 17], true)],
            ]);
        }
        foreach (range(3301, 3307) as $number) {
            $code = 'insee-t-'.$number;
            $dataset = Dataset::query()->updateOrCreate(['slug' => $code], ['source_id' => $source->id, 'name' => 'INSEE T_'.$number, 'description' => 'Dépenses des administrations publiques ventilées par fonction COFOG.', 'accounting_system' => 'national_accounts', 'scope' => 'general_government', 'frequency' => 'annual', 'unit' => 'billion_eur', 'first_year' => 1995, 'last_year' => 2024, 'metadata' => ['accounting_scope' => 'general_government', 'reporting_period' => '1995-2024', 'unit' => 'billion_eur', 'status' => 'executed', 'classification' => 'cofog']]);
            DatasetFile::query()->updateOrCreate(['slug' => $code], ['dataset_id' => $dataset->id, 'expected_filename' => 'T_'.$number.'.xlsx', 'metadata' => ['importer' => 'insee_cofog_xlsx', 'accounting_scope' => 'general_government', 'is_consolidated' => in_array($number, [3301, 3307], true)]]);
        }
    }
}
