<?php

namespace App\Services\Rap;

use Illuminate\Support\Facades\File;

class RapDivergenceAnalyzer
{
    public function __construct(private readonly RapPdfExtractor $extractor) {}

    /**
     * @param array<string,mixed> $report
     * @param array<int,array<string,mixed>> $entries
     * @return array<string,mixed>
     */
    public function analyze(array $report, array $entries, string $rawPath, string $processedPath): array
    {
        $byProgram = [];
        foreach ($entries as $entry) {
            if (isset($entry['program'])) {
                $byProgram[(string) $entry['program']] = $entry;
            }
        }
        $plrg = $this->plrgTotals();

        $items = [];
        foreach ($report['divergences'] ?? [] as $divergence) {
            $program = (string) ($divergence['program'] ?? '');
            $parsedPath = $processedPath.'/'.$program.'.json';
            $parsed = is_file($parsedPath) ? json_decode((string) File::get($parsedPath), true) : [];
            $pdfPath = $rawPath.'/P'.$program.'.pdf';
            $text = is_file($pdfPath) ? $this->safeExtract($pdfPath) : '';
            $hasFdc = (bool) preg_match('/FdC|AdP|fonds de concours|attributions de produits/iu', $text);
            $hasInclusiveTotal = (bool) preg_match('/Total\s+(?:des\s+)?(?:AE|CP).*?y\.c\.\s+FdC\s+et\s+AdP/iu', $text);
            $pages = $this->pagesUsed($text);
            $subActions = (int) ($parsed['counts']['sub_actions'] ?? 0);
            $actionCount = (int) ($parsed['counts']['actions'] ?? count($parsed['actions'] ?? []));

            foreach (($divergence['differences'] ?? []) as $key => $difference) {
                $reference = (float) ($difference['programme_total'] ?? 0);
                $extracted = (float) ($difference['actions_sum'] ?? 0);
                $absolute = abs($extracted - $reference);
                $relative = $reference == 0.0 ? null : round($absolute / abs($reference), 8);
                $category = $this->category($absolute, $relative, $hasFdc, $hasInclusiveTotal, $subActions, $actionCount, $parsed);
                $plrgTotal = $plrg[$program][$key] ?? null;
                $rapDifference = $extracted - $reference;
                $plrgDifference = $plrgTotal === null ? null : $reference - $plrgTotal;
                $previousCategory = $category === 'DUPLICATE_ACTION' ? 'DUPLICATE_ACTION' : null;
                if ($category === 'DUPLICATE_ACTION') {
                    // Sub-actions alone are not proof of a parser duplicate.
                    // Keep the old label for traceability, but downgrade the
                    // current classification until duplicate lines are proven.
                    $category = 'UNKNOWN';
                }
                if ($plrgTotal !== null && abs($rapDifference) <= 1000 && abs($plrgDifference) > 1000) {
                    $category = 'RAP_PLRG_SCOPE_DIFFERENCE';
                } elseif ($plrgTotal !== null && abs($rapDifference) > 1000 && abs($plrgDifference) <= 1000) {
                    $category = 'TABLE_TOTAL_MISMATCH';
                }
                $items[] = [
                    'program' => $program,
                    'mission' => $parsed['mission'] ?? null,
                    'measurement_type' => str_contains($key, 'ae') ? 'commitment_authorization' : 'payment_credit',
                    'flow_type' => 'expenditure',
                    'total_extracted_actions' => $extracted,
                    'total_rap_same_measure' => $reference,
                    'reference_total_plrg' => $plrgTotal,
                    'difference_actions_to_rap' => $rapDifference,
                    'difference_rap_to_plrg' => $plrgDifference,
                    'absolute_difference' => $absolute,
                    'relative_difference' => $relative,
                    'fdc_adp_present' => $hasFdc,
                    'total_including_fdc_adp_present' => $hasInclusiveTotal,
                    'sub_actions_present' => $subActions > 0,
                    'actions_extracted' => $actionCount,
                    'affected_actions' => $this->affectedActions($parsed, $key),
                    'source_pages_used' => $pages,
                    'category' => $category,
                    'previous_category' => $previousCategory,
                    'classification_history' => $previousCategory === null ? [] : [['category' => $previousCategory, 'reason' => 'Présence de sous-actions בלבד, insuffisante pour démontrer un doublon.']],
                    'probable_cause' => $this->cause($category),
                    'review_required' => true,
                    'automatically_accepted' => false,
                ];
            }
        }

        $summary = [];
        foreach ($items as $item) {
            $summary[$item['category']] = ($summary[$item['category']] ?? 0) + 1;
        }
        ksort($summary);
        $programs = array_values(array_unique(array_map(fn (array $item): string => (string) $item['program'], $items)));
        $parsedPrograms = (int) ($report['parsed'] ?? 0);
        $accountingScope = count(array_filter($items, fn (array $item): bool => $item['category'] === 'RAP_PLRG_SCOPE_DIFFERENCE'));
        $validatedPrograms = max(0, $parsedPrograms - count($programs));
        $result = ['year' => 2024, 'generated_at' => now()->toIso8601String(), 'items' => $items, 'counts_by_category' => $summary, 'phase1_duplicate_audit' => array_values(array_filter($items, fn (array $item): bool => $item['previous_category'] === 'DUPLICATE_ACTION')), 'comparison' => ['before' => ['validated_programs' => 139, 'review_required_programs' => 39, 'table_total_mismatch' => 42, 'unknown' => 11], 'after' => ['validated_programs' => $validatedPrograms, 'review_required_programs' => count($programs), 'table_total_mismatch' => $summary['TABLE_TOTAL_MISMATCH'] ?? 0, 'unknown' => $summary['UNKNOWN'] ?? 0]], 'global_summary' => ['parsed_programs' => $parsedPrograms, 'validated_programs' => $validatedPrograms, 'review_required_programs' => count($programs), 'structural_errors' => count($report['errors'] ?? [])], 'divergence_summary' => ['programs' => count($programs), 'checks' => count($items), 'table_total_mismatch' => $summary['TABLE_TOTAL_MISMATCH'] ?? 0, 'unknown' => $summary['UNKNOWN'] ?? 0], 'summary' => ['validated_programs' => $validatedPrograms, 'review_required_programs' => count($programs), 'structural_errors' => count($report['errors'] ?? []), 'divergences_by_reason' => $summary, 'parser_errors_confirmed' => 0, 'accounting_scope_differences' => $accountingScope, 'unexplained_divergences' => count($items) - $accountingScope], 'rule' => 'Seules les divergences démontrées peuvent être acceptées ; les cas incertains restent review_required.'];
        File::put($processedPath.'/divergence-report.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $markdown = "# Divergences RAP 2024\n\n| Catégorie | Nombre |\n|---|---:|\n";
        foreach ($summary as $category => $count) {
            $markdown .= "| {$category} | {$count} |\n";
        }
        $markdown .= "\nTotal : ".count($items)." divergences. Toutes restent `review_required`.\n";
        $markdown .= "\n## Résumé global\n\n- Programmes parsés : {$parsedPrograms}\n- Programmes validés : {$validatedPrograms}\n- Programmes en revue : ".count($programs)."\n- Erreurs structurelles : ".count($report['errors'] ?? [])."\n- Différences de périmètre comptable RAP/PLRG : {$accountingScope}\n- Divergences inexpliquées : ".(count($items) - $accountingScope)."\n";
        File::put($processedPath.'/divergence-report.md', $markdown);

        return $result;
    }

    /** @return array<string,array<string,float>> */
    private function plrgTotals(): array
    {
        $result = [];
        foreach (['ae' => 'Annexe1-Etat_AE-2024.csv', 'cp' => 'Annexe1-Etat_CP-2024.csv'] as $kind => $filename) {
            $path = base_path('data/2024/budget-etat/execution/'.$filename);
            if (! is_file($path) || ($handle = fopen($path, 'rb')) === false) continue;
            $header = fgetcsv($handle, 0, ';');
            if (! is_array($header)) { fclose($handle); continue; }
            $indexes = array_flip($header);
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $program = isset($row[1]) && preg_match('/(\d{3})\s*$/u', $row[1], $match) ? $match[1] : null;
                if ($program === null) continue;
                $values = $kind === 'ae' ? ['ae_lfi' => 'LFI', 'ae_consumed' => 'AE_Consommees'] : ['cp_lfi' => 'LFI', 'cp_consumed' => 'Depenses_constatees'];
                foreach ($values as $key => $column) {
                    if (! isset($indexes[$column], $row[$indexes[$column]])) continue;
                    $value = str_replace([' ', '\xC2\xA0', '.'], '', $row[$indexes[$column]]);
                    $value = (float) str_replace(',', '.', $value);
                    $result[$program][$key] = ($result[$program][$key] ?? 0.0) + $value;
                }
            }
            fclose($handle);
        }
        return $result;
    }

    /**
     * @param array<string,mixed> $parsed
     * @return list<array{position:int,code:mixed,label:mixed,page:null,value:mixed,extracted_lines:mixed}>
     */
    private function affectedActions(array $parsed, string $key): array
    {
        $field = $key;
        $result = [];
        $cumulative = 0;
        foreach (($parsed['actions'] ?? []) as $position => $action) {
            if (! is_array($action)) continue;
            if (! array_key_exists($field, $action) || $action[$field] === null) continue;
            $contributes = ($action['contributes_to_program_total'] ?? false) === true;
            if ($contributes) $cumulative += (int) $action[$field];
            $result[] = ['position' => $position + 1, 'code' => $action['code'] ?? null, 'label' => $action['label'] ?? null, 'hierarchy_level' => $action['hierarchy_level'] ?? null, 'parent_action_code' => $action['parent_action_code'] ?? null, 'contributes_to_program_total' => $contributes, 'page' => null, 'value' => $action[$field] ?? null, 'sum_contribution' => $contributes ? $action[$field] : 0, 'cumulative_total' => $cumulative, 'extracted_lines' => $action['extracted_lines'] ?? []];
        }
        return $result;
    }

    private function safeExtract(string $path): string
    {
        try { return $this->extractor->extract($path); } catch (\Throwable) { return ''; }
    }

    /** @return list<int> */
    private function pagesUsed(string $text): array
    {
        if ($text === '') return [];
        $pages = [];
        foreach (preg_split('/\f/u', $text) ?: [] as $index => $page) {
            if (preg_match('/2024\s*\/\s*(?:PR[ÉE]SENTATION|AUTORISATIONS|CR[ÉE]DITS)|Total des (?:AE|CP)/iu', $page)) $pages[] = $index + 1;
        }
        return array_values(array_unique($pages));
    }

    /** @param array<string,mixed> $parsed */
    private function category(float $absolute, ?float $relative, bool $fdc, bool $inclusive, int $subActions, int $actions, array $parsed): string
    {
        if ($fdc && $inclusive && $relative !== null && $relative < 0.01) return 'FDC_ADP';
        if ($subActions > 0 && $relative !== null && $relative > 0.01) return 'DUPLICATE_ACTION';
        if (($parsed['format'] ?? null) === 'institutional_credits') return 'SPECIAL_LAYOUT';
        if ($absolute <= 1000) return 'ROUNDING';
        if ($actions === 0) return 'MISSING_ACTION';
        return 'UNKNOWN';
    }

    private function cause(string $category): string
    {
        return match ($category) {
            'FDC_ADP' => 'Écart compatible avec un total incluant explicitement les FdC/AdP.',
            'DUPLICATE_ACTION' => 'Présence de sous-actions ; un double-compte ou une règle de consolidation doit être vérifié.',
            'TABLE_TOTAL_MISMATCH' => 'La somme des actions ne correspond pas au total du RAP alors que le RAP et le PLRG concordent : extraction ou périmètre de tableau à auditer.',
            'RAP_PLRG_SCOPE_DIFFERENCE' => 'Le total des actions correspond au RAP, mais le total RAP diffère du PLRG : différence de référence ou de périmètre.',
            'ROUNDING' => 'Écart inférieur ou égal à la tolérance documentée.',
            'MISSING_ACTION' => 'Aucune action exploitable n’a été extraite.',
            'SPECIAL_LAYOUT' => 'Mise en page ou terminologie budgétaire spécifique.',
            default => 'Cause non démontrée : revue manuelle nécessaire.',
        };
    }
}
