<?php

namespace Tests\Feature\Imports;

use App\Enums\AccountingBasis;
use App\Enums\MeasurementType;
use App\Models\DatasetFile;
use App\Models\FinancialObservation;
use App\Services\Imports\InseePublicAccountsXlsxImporter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InseePublicAccountsXlsxImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_an_insee_national_accounts_table_with_provenance(): void
    {
        $this->seed(DatabaseSeeder::class);
        $file = DatasetFile::query()->where('slug', 'insee-t-3203')->firstOrFail();

        $batch = app(InseePublicAccountsXlsxImporter::class)->import($file, base_path('data/series-historiques/insee/T_3203_fr.xlsx'));
        $observation = FinancialObservation::query()->firstOrFail();

        $this->assertGreaterThan(0, $batch->rows_imported);
        $this->assertSame(1978, $observation->year);
        $this->assertSame(MeasurementType::Expenditure, $observation->measurement_type);
        $this->assertSame(AccountingBasis::NationalAccounts, $observation->accounting_basis);
        $this->assertSame('billion_eur', $observation->metadata['original_unit']);
        $this->assertNotNull($observation->source_row_number);
    }
}
