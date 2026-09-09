<?php

namespace App\Console\Commands;

use App\Enums\AccountingBasis;
use App\Enums\AeCp;
use App\Enums\BudgetStage;
use App\Enums\FinancialMeasure;
use App\Enums\FlowType;
use App\Enums\MeasurementType;
use App\Enums\ObservationStatus;
use App\Models\AccountingScope;
use App\Models\Classification;
use App\Models\ClassificationItem;
use App\Models\Dataset;
use App\Models\DatasetFile;
use App\Models\FinancialObservation;
use App\Models\Source;
use App\Services\Rap\RapCatalogCrawler;
use App\Services\Rap\RapDivergenceAnalyzer;
use App\Services\Rap\RapPdfExtractor;
use App\Services\Rap\RapTextParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ImportRap extends Command
{
    protected $signature = 'dataset:import-rap {year} {--download-only} {--parse-only} {--program=} {--catalog=} {--force}';

    protected $description = 'Télécharge et importe les RAP depuis la page officielle Budget.gouv';

    public function handle(RapCatalogCrawler $crawler, RapPdfExtractor $extractor, RapTextParser $parser, RapDivergenceAnalyzer $divergenceAnalyzer): int
    {
        $year = (int) $this->argument('year');
        if ($year !== 2024) {
            $this->error('Seul l’exercice 2024 est pris en charge.');

            return self::FAILURE;
        }
        $raw = base_path("data/raw/rap/{$year}");
        $processed = base_path("data/processed/rap/{$year}");
        File::ensureDirectoryExists($raw);
        File::ensureDirectoryExists($processed);
        $catalogPath = $processed.'/catalog.json';
        $entries = [];
        $discovered = 0;
        $download = ['downloaded' => 0, 'failed' => []];
        $providedCatalog = $this->option('catalog');
        if ($providedCatalog !== null) {
            $catalogPath = base_path((string) $providedCatalog);
        }
        if (! $this->option('parse-only') && $providedCatalog === null) {
            try {
                $entries = $crawler->discover();
                $discovered = count($entries);
                File::put($catalogPath, json_encode(['source' => RapCatalogCrawler::PAGE_URL, 'discovered' => count($entries), 'entries' => $entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } catch (Throwable $e) {
                $this->error('Découverte impossible : '.$e->getMessage());
                $this->line('RAP 2024 : discovered=0 downloaded=0 failed=0');

                return self::FAILURE;
            }
        } elseif (is_file($catalogPath)) {
            $catalog = json_decode((string) File::get($catalogPath), true) ?? [];
            $entries = $catalog['entries'] ?? [];
            $discovered = (int) ($catalog['discovered'] ?? count($entries));
        } elseif ($this->option('parse-only')) {
            $this->error("Catalogue RAP absent : {$catalogPath}");

            return self::FAILURE;
        }
        if (! $this->option('parse-only')) {
            foreach ($entries as $entry) {
                if ($this->option('program') !== null && (string) $this->option('program') !== $entry['program']) {
                    continue;
                }
                $path = $raw.'/P'.$entry['program'].'.pdf';
                if (is_file($path) && ! $this->option('force')) {
                    continue;
                }
                try {
                    $response = Http::withHeaders(['Accept' => 'application/pdf', 'User-Agent' => 'Mais-ou-vont-mes-impots/1.0'])->timeout(180)->retry(3, 1500)->get($entry['url']);
                    if ($response->failed() || ! str_starts_with(strtolower((string) $response->header('Content-Type')), 'application/pdf')) {
                        throw new \RuntimeException('Réponse non PDF ou HTTP '.$response->status());
                    }
                    File::put($path, $response->body());
                    $download['downloaded']++;
                } catch (Throwable $e) {
                    $download['failed'][] = ['program' => $entry['program'], 'url' => $entry['url'], 'error' => $e->getMessage()];
                    $this->error('P'.$entry['program'].' : '.$e->getMessage());
                }
            }
        }
        if ($this->option('download-only')) {
            $this->line(sprintf('RAP 2024 : discovered=%d downloaded=%d failed=%d', $discovered, $download['downloaded'], count($download['failed'])));

            return self::SUCCESS;
        }

        $source = Source::query()->updateOrCreate(['slug' => 'budget-gouv-plrg-2024'], ['name' => 'PLRG/RAP 2024', 'publisher' => 'Direction du Budget', 'homepage_url' => RapCatalogCrawler::PAGE_URL, 'description' => 'Rapports annuels de performances 2024.', 'is_official' => true]);
        $dataset = Dataset::query()->updateOrCreate(['slug' => 'state-budget-rap-2024'], ['source_id' => $source->id, 'name' => 'Rapports annuels de performances 2024', 'description' => 'Dépenses de l’État par programme, action et sous-action.', 'source_url' => RapCatalogCrawler::PAGE_URL, 'publication_title' => 'PLRG 2024 — RAP', 'publication_date' => '2025-04-16', 'downloaded_at' => now(), 'license_name' => 'Licence ouverte / Etalab', 'year' => 2024, 'accounting_system' => 'budgetary', 'scope' => 'french_state_budget', 'unit' => 'EUR', 'metadata' => ['accounting_scope' => 'french_state_budget', 'reporting_period' => '2024', 'status' => 'executed', 'unit' => 'EUR']]);
        $report = ['discovered' => $discovered, 'downloaded' => $download['downloaded'], 'failed' => $download['failed'], 'parsed' => 0, 'programmes' => 0, 'actions' => 0, 'sub_actions' => 0, 'errors' => [], 'divergences' => [], 'special_diagnostics' => []];
        $scope = AccountingScope::query()->where('code', 'french_state_budget')->firstOrFail();
        $classification = Classification::query()->firstOrCreate(['code' => 'state_budget_programme_action'], ['name' => 'Budget de l’État — mission, programme, action', 'description' => 'Hiérarchie des RAP du budget de l’État.']);
        $missionMap = $this->missionMap();
        foreach ($entries as $entry) {
            if ($this->option('program') !== null && (string) $this->option('program') !== $entry['program']) {
                continue;
            }
            $pdf = $raw.'/P'.$entry['program'].'.pdf';
            $jsonPath = $processed.'/'.$entry['program'].'.json';
            if (! is_file($pdf)) {
                $report['errors'][] = ['program' => $entry['program'], 'error' => 'PDF absent'];

                continue;
            }
            try {
                $parsed = $parser->parse($extractor->extract($pdf), $entry['program'], $entry['name']);
                $parsed['source_url'] = $entry['url'];
                $parsed['pdf_sha256'] = hash_file('sha256', $pdf);
                $parsed['mission'] = $missionMap[$entry['program']] ?? null;
                if (in_array((string) $entry['program'], ['501', '511', '521', '531', '532', '533', '541', '542'], true)) {
                    $report['special_diagnostics'][] = ['program' => $entry['program'], 'terminology_found' => $parsed['format'] ?? 'none', 'available_amounts' => array_keys($parsed['actions'][0]['special_measurements'] ?? []), 'proposed_measurement_type' => array_keys($parsed['actions'][0]['special_measurements'] ?? []), 'importable' => ($parsed['format'] ?? null) === 'institutional_credits', 'reason' => ($parsed['format'] ?? null) === 'institutional_credits' ? 'Colonnes explicites, sans conversion AE/CP.' : 'Tableau spécifique non structuré.'];
                }
                if ($parsed['validation']['differences'] !== []) {
                    $report['divergences'][] = ['program' => $entry['program'], 'differences' => $parsed['validation']['differences']];
                }
                File::put($jsonPath, json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->importParsed($dataset, $entry, $pdf, $parsed, $classification, $scope);
                $report['parsed']++;
                $report['programmes']++;
                $report['actions'] += $parsed['counts']['actions'];
                $report['sub_actions'] += $parsed['counts']['sub_actions'];
            } catch (Throwable $e) {
                File::put($jsonPath, json_encode(['program' => $entry, 'review_required' => true, 'error' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $report['errors'][] = ['program' => $entry['program'], 'error' => $e->getMessage()];
                if (in_array((string) $entry['program'], ['501', '511', '521', '531', '532', '533', '541', '542'], true)) {
                    $report['special_diagnostics'][] = ['program' => $entry['program'], 'terminology_found' => 'specific institutional format', 'available_amounts' => [], 'proposed_measurement_type' => [], 'importable' => false, 'reason' => $e->getMessage()];
                }
            }
        }
        $divergenceAnalyzer->analyze($report, $entries, $raw, $processed);
        $report['divergence_report'] = $processed.'/divergence-report.json';
        File::put($processed.'/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line(sprintf('RAP 2024 : discovered=%d downloaded=%d failed=%d parsed=%d programmes=%d actions=%d sous-actions=%d', $report['discovered'], $report['downloaded'], count($report['failed']), $report['parsed'], $report['programmes'], $report['actions'], $report['sub_actions']));

        return $report['errors'] !== [] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string,mixed>  $entry
     * @param  array<string,mixed>  $parsed
     */
    private function importParsed(Dataset $dataset, array $entry, string $pdf, array $parsed, Classification $classification, AccountingScope $scope): void
    {
        DB::transaction(function () use ($dataset, $entry, $pdf, $parsed, $classification, $scope): void {
            $reviewRequired = false;
            foreach ($parsed['actions'] as $action) {
                $reviewRequired = $reviewRequired || (($action['review_required'] ?? false) === true);
            }
            $file = DatasetFile::query()->updateOrCreate(['slug' => 'rap-2024-'.$entry['program']], ['dataset_id' => $dataset->id, 'expected_filename' => basename($pdf), 'metadata' => ['program' => $entry['program'], 'program_name' => $entry['name'], 'source_url' => $entry['url'], 'review_required' => $reviewRequired]]);
            $checksum = hash_file('sha256', $pdf);
            $batch = $file->importBatches()->firstOrCreate(['checksum' => $checksum], ['dataset_id' => $dataset->id, 'filename' => basename($pdf), 'status' => 'completed', 'started_at' => now(), 'completed_at' => now(), 'rows_read' => count($parsed['actions']), 'rows_imported' => count($parsed['actions']) * 4, 'metadata' => ['parser' => $parsed['parser']]]);
            $mission = $parsed['mission'] ?? 'Mission non renseignée dans la page RAP';
            $missionItem = ClassificationItem::query()->firstOrCreate(['classification_id' => $classification->id, 'official_label' => $mission], ['slug' => Str::slug($mission), 'metadata' => ['level' => 'mission']]);
            $programItem = ClassificationItem::query()->updateOrCreate(['classification_id' => $classification->id, 'code' => $entry['program']], ['parent_id' => $missionItem->id, 'official_label' => $entry['name'], 'slug' => 'programme-'.$entry['program'], 'metadata' => ['level' => 'programme', 'mission' => $mission]]);
            foreach ($parsed['actions'] as $row) {
                $item = ClassificationItem::query()->updateOrCreate(['classification_id' => $classification->id, 'code' => $entry['program'].'-'.$row['code']], ['parent_id' => $programItem->id, 'official_label' => $row['label'], 'slug' => 'programme-'.$entry['program'].'-action-'.str_replace('.', '-', $row['code']), 'metadata' => ['level' => $row['hierarchy_level'] ?? (str_contains($row['code'], '.') ? 'sub_action' : 'action'), 'parent_action_code' => $row['parent_action_code'] ?? null, 'contributes_to_program_total' => $row['contributes_to_program_total'] ?? ! str_contains($row['code'], '.')]]);
                if (($parsed['format'] ?? null) === 'institutional_credits') {
                    foreach (($row['special_measurements'] ?? []) as $measure => $amount) {
                        $financialMeasure = FinancialMeasure::tryFrom((string) $measure);
                        if ($financialMeasure === null) {
                            continue;
                        }
                        $stage = $financialMeasure === FinancialMeasure::ExpenditureRecorded ? BudgetStage::Execution : BudgetStage::InitialBudget;
                        FinancialObservation::query()->updateOrCreate(['dataset_file_id' => $file->id, 'source_identifier' => $entry['program'].'|'.$row['code'].'|'.$measure], ['dataset_id' => $dataset->id, 'import_batch_id' => $batch->id, 'year' => 2024, 'accounting_scope_id' => $scope->id, 'institution_scope_id' => $scope->id, 'classification_item_id' => $item->id, 'category_id' => $item->id, 'status' => $stage === BudgetStage::Execution ? ObservationStatus::Executed : ObservationStatus::InitialEstimate, 'measurement_type' => MeasurementType::Expenditure, 'accounting_basis' => AccountingBasis::Budgetary, 'budget_stage' => $stage, 'ae_cp' => null, 'is_consolidated' => false, 'measure' => $financialMeasure, 'flow_type' => FlowType::Expenditure, 'amount' => $amount, 'currency' => 'EUR', 'metadata' => ['source_url' => $entry['url'], 'source_page' => null, 'raw_label' => $row['label'], 'source_field' => $measure, 'review_required' => false]]);
                    }

                    continue;
                }
                foreach ([['ae_lfi', AeCp::Ae], ['ae_consumed', AeCp::Ae], ['cp_lfi', AeCp::Cp], ['cp_consumed', AeCp::Cp]] as [$field, $aeCp]) {
                    if ($row[$field] === null) {
                        continue;
                    } $stage = str_contains($field, 'lfi') ? BudgetStage::InitialBudget : BudgetStage::Execution;
                    FinancialObservation::query()->updateOrCreate(['dataset_file_id' => $file->id, 'source_identifier' => $entry['program'].'|'.$row['code'].'|'.$field], ['dataset_id' => $dataset->id, 'import_batch_id' => $batch->id, 'year' => 2024, 'accounting_scope_id' => $scope->id, 'institution_scope_id' => $scope->id, 'classification_item_id' => $item->id, 'category_id' => $item->id, 'status' => $stage === BudgetStage::Execution ? ObservationStatus::Executed : ObservationStatus::InitialEstimate, 'measurement_type' => MeasurementType::Expenditure, 'accounting_basis' => AccountingBasis::Budgetary, 'budget_stage' => $stage, 'ae_cp' => $aeCp, 'is_consolidated' => false, 'measure' => $aeCp === AeCp::Ae ? 'commitment_authorization' : 'payment_credit', 'flow_type' => FlowType::Expenditure, 'amount' => $row[$field], 'currency' => 'EUR', 'metadata' => ['source_url' => $entry['url'], 'source_page' => null, 'raw_label' => $row['label'], 'source_field' => $field, 'review_required' => $row['review_required']]]);
                }
            }
        });
    }

    /** @return array<string,string> */
    private function missionMap(): array
    {
        $path = base_path('data/2024/budget-etat/execution/Annexe1-Etat_AE-2024.csv');
        if (! is_file($path) || ($handle = fopen($path, 'rb')) === false) {
            return [];
        } fgetcsv($handle, 0, ';');
        $map = [];
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) >= 2 && preg_match('/[-–]\s*(\d{3})\s*$/u', $row[1], $m)) {
                $map[$m[1]] = trim($row[0]);
            }
        } fclose($handle);

        return $map;
    }
}
