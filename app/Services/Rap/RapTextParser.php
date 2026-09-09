<?php

namespace App\Services\Rap;

use RuntimeException;

class RapTextParser
{
    /** @return array<string, mixed> */
    public function parse(string $text, string $program, string $programName): array
    {
        $anchorPattern = '/2024\s*\/\s*PR[ÉE]SENTATION\s+PAR\s+ACTION\s+ET\s+TITRE\s+DES\s+CR[ÉE]DITS\s+(?:OUVERTS\/VOT[ÉE]S|OUVERTS)\s+ET\s+DES\s+CR[ÉE]DITS\s+CONSOMM[ÉE]S/iu';
        // Find the offset in the original text. Using an offset obtained
        // from whitespace-normalized UTF-8 text is unsafe because byte
        // lengths change around accented characters.
        if (! preg_match($anchorPattern, $text, $anchor, PREG_OFFSET_CAPTURE)) {
            $special = $this->parseSpecialInstitution($text, $program, $programName);
            if ($special !== null) {
                return $special;
            }
            throw new RuntimeException('Section 2024 par action introuvable.');
        }

        $section = substr($text, (int) $anchor[0][1]);
        $end = preg_match('/2023\s*\/\s*PR[ÉE]SENTATION PAR ACTION/iu', $section, $match, PREG_OFFSET_CAPTURE)
            ? (int) $match[0][1]
            : strlen($section);
        $section = substr($section, 0, $end);
        $parts = preg_split('/2024\s*\/\s*CR[ÉE]DITS DE PAIEMENT/iu', $section, 2) ?: [$section];
        $ae = $this->parseRows($parts[0]);
        $cp = $this->parseRows($parts[1] ?? '');
        $byCode = [];
        foreach ($ae as $action) {
            $byCode[$action['code']] = $action;
        }
        foreach ($cp as $action) {
            $cpRows = $action['amount_rows'] ?? [];
            $byCode[$action['code']]['cp_lfi'] = $this->rowTotal($cpRows[0] ?? []);
            $byCode[$action['code']]['cp_consumed'] = $this->rowTotal($cpRows[1] ?? []);
            $byCode[$action['code']]['cp_amounts'] = $action['amounts'];
            $byCode[$action['code']]['review_required'] = $byCode[$action['code']]['review_required'] || count($action['amounts']) < 6;
        }
        $actions = array_values(array_filter($byCode, fn (array $action): bool => $action['amounts'] !== []));
        if ($actions === []) {
            throw new RuntimeException('Aucune action chiffrée détectée dans la section 2024.');
        }

        $result = [
            'year' => 2024,
            'program' => ['code' => $program, 'name' => $programName],
            'actions' => $actions,
            'parser' => ['anchor' => '2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS', 'amount_mapping' => 'layout-column-order-v1'],
            'review_required' => false,
            'warnings' => [],
        ];
        $result['validation'] = $this->validateTotals($section, $actions);
        $result['review_required'] = $result['validation']['review_required'];
        $result['counts'] = ['actions' => count($actions), 'sub_actions' => count(array_filter($actions, fn (array $a): bool => str_contains($a['code'], '.')))];

        return $result;
    }

    /**
     * Parse the distinct parliamentary/constitutional format without mapping
     * its terms to AE or CP.
     *
     * @return array<string,mixed>|null
     */
    private function parseSpecialInstitution(string $text, string $program, string $programName): ?array
    {
        if (! preg_match('/(?:Intitulé de l.action|Dotation 2024).*?(?:Crédits ouverts|Dépenses constatées)/iu', $text, $header, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $actions = [];
        $table = substr($text, (int) $header[0][1]);
        $table = preg_split('/\f/u', $table, 2)[0] ?? $table;
        foreach (preg_split('/\R/u', $table) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^(?:Intitulé|Total|Dotation|Crédits|Dépenses)/iu', $line)) {
                continue;
            }
            preg_match_all('/\d{1,3}(?:[ \x{00a0}]\d{3})+|\d+/u', $line, $matches);
            $numbers = array_map(fn (string $value): int => $this->amount($value), $matches[0]);
            if (count($numbers) < 3) {
                continue;
            }
            $label = trim((string) preg_replace('/\s+(?:\d{1,3}(?:[ \x{00a0}]\d{3})+|\d+).*$/u', '', $line));
            if ($label === '') {
                continue;
            }
            $last = count($numbers) >= 4
                ? [$numbers[0], $numbers[2], $numbers[3]]
                : array_slice($numbers, -3);
            $actions[] = [
                'code' => (string) (count($actions) + 1),
                'label' => $label,
                'hierarchy_level' => 'action',
                'parent_action_code' => null,
                'contributes_to_program_total' => false,
                'amounts' => $last,
                'amount_rows' => [],
                'special_measurements' => ['allocation' => $last[0], 'credits_opened' => $last[1], 'expenditure_recorded' => $last[2]],
                'ae_lfi' => null, 'ae_consumed' => null, 'cp_lfi' => null, 'cp_consumed' => null,
                'titles' => [], 'review_required' => false,
            ];
        }
        if ($actions === []) {
            return null;
        }

        return [
            'year' => 2024,
            'program' => ['code' => $program, 'name' => $programName],
            'format' => 'institutional_credits',
            'actions' => $actions,
            'parser' => ['anchor' => 'institutional credits table', 'amount_mapping' => 'allocation-opened-recorded-v1'],
            'review_required' => false,
            'warnings' => ['Ces montants ne sont pas des AE/CP et ne doivent pas être convertis.'],
            'validation' => ['tolerance_eur' => 1000, 'totals' => [], 'differences' => [], 'review_required' => false],
            'counts' => ['actions' => count($actions), 'sub_actions' => 0],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function parseRows(string $section): array
    {
        $lines = preg_split('/\R/u', $section) ?: [];
        $actions = [];
        $current = null;
        $numberLines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($current !== null && preg_match('/^(?:Total des|Ouvertures?\s*\/\s*annulations|202[34]\s*\/)/iu', $line)) {
                $actions[] = $this->finish($current, $numberLines);
                $current = null;
                $numberLines = [];

                continue;
            }
            if (preg_match('/^(\d{1,3}(?:\.\d{1,3})?)\s*[–—-]\s*(.+)$/u', $line, $row)) {
                if ($current !== null) {
                    $actions[] = $this->finish($current, $numberLines);
                }
                [$label, $columns] = $this->splitLabelColumns($row[2]);
                $current = ['code' => $row[1], 'label' => $label];
                $numberLines = $columns === null ? [] : [$columns];

                continue;
            }
            if ($current !== null && $this->isAmountLine($line)) {
                $numberLines[] = $line;

                continue;
            }
            // In many RAPs the action label wraps onto a second line and the
            // consumption amounts are printed after that remaining label.
            // Keep only the numeric suffix; otherwise the consumed row is
            // silently lost.
            if ($current !== null) {
                [, $columns] = $this->splitLabelColumns($line);
                if ($columns !== null) {
                    $numberLines[] = $columns;
                }
            }
        }
        if ($current !== null) {
            $actions[] = $this->finish($current, $numberLines);
        }

        return array_values(array_filter($actions, fn (array $action): bool => $action['amounts'] !== []));
    }

    /**
     * @param  array{code:string,label:string}  $current
     * @param  array<int,string>  $numberLines
     * @return array<string,mixed>
     */
    private function finish(array $current, array $numberLines): array
    {
        $amountRows = [];
        foreach ($numberLines as $line) {
            if (preg_match('/^[+-]?[\d\s.,]+$/u', $line)) {
                $amountRows[] = $this->amountColumns($line);
            }
        }
        $amounts = array_merge(...$amountRows);
        $lfi = $amountRows[0] ?? [];
        $consumed = $amountRows[1] ?? [];
        // The number of title columns varies between RAPs. The total is the
        // last populated column (the final column may include FdC and AdP on
        // the LFI row). Never rely on a fixed title index here.
        $total = fn (array $row): ?int => $this->rowTotal($row);

        return [
            'code' => $current['code'],
            'label' => $current['label'],
            'hierarchy_level' => str_contains($current['code'], '.') ? 'sub_action' : 'action',
            'parent_action_code' => str_contains($current['code'], '.') ? explode('.', $current['code'])[0] : null,
            'contributes_to_program_total' => ! str_contains($current['code'], '.'),
            'amounts' => $amounts,
            'amount_rows' => $amountRows,
            'extracted_lines' => $numberLines,
            'ae_lfi' => $total($lfi),
            'ae_consumed' => $total($consumed),
            'cp_lfi' => null,
            'cp_consumed' => null,
            'titles' => [],
            'review_required' => count($amounts) < 6,
        ];
    }

    private function isAmountLine(string $line): bool
    {
        return $line !== '' && (bool) preg_match('/^[+\-]?\s*[\d\s.,]+$/u', $line);
    }

    /** @return array{0:string,1:string|null} */
    private function splitLabelColumns(string $value): array
    {
        if (preg_match('/^(.+?)\s{2,}([+\-]?\d[\d\s.,]*(?:\s{2,}[+\-]?\d[\d\s.,]*)*)$/u', $value, $match)) {
            return [trim($match[1]), trim($match[2])];
        }

        return [trim($value), null];
    }

    /**
     * @param  array<int,array<string,mixed>>  $actions
     * @return array{tolerance_eur:int,totals:array<string,int|null>,differences:array<string,array{actions_sum:int,programme_total:int,difference:int}>,review_required:bool}
     */
    private function validateTotals(string $section, array $actions): array
    {
        $lines = preg_split('/\R/u', $section) ?: [];
        $totals = [];
        foreach (['ae_lfi' => 'Total des AE prévues en LFI', 'ae_consumed' => 'Total des AE consommées', 'cp_lfi' => 'Total des CP prévus en LFI', 'cp_consumed' => 'Total des CP consommés'] as $key => $label) {
            $totals[$key] = $this->totalAfter($lines, $label);
        }
        $differences = [];
        foreach ($totals as $key => $total) {
            // Programme totals correspond to the action level. When a RAP
            // also publishes sub-actions, parent and child rows must not be
            // added together or the programme is double-counted.
            $rootActions = array_filter($actions, fn (array $row): bool => ($row['contributes_to_program_total'] ?? false) === true);
            $sum = array_sum(array_map(fn (array $row): int => (int) ($row[$key] ?? 0), $rootActions));
            if ($total !== null && abs($sum - $total) > 1000) {
                $differences[$key] = ['actions_sum' => $sum, 'programme_total' => $total, 'difference' => $sum - $total];
            }
        }

        return ['tolerance_eur' => 1000, 'totals' => $totals, 'differences' => $differences, 'review_required' => $differences !== []];
    }

    /** @param array<int, string> $lines */
    private function totalAfter(array $lines, string $label): ?int
    {
        foreach ($lines as $index => $line) {
            if (! str_contains(mb_strtolower($line), mb_strtolower($label))) {
                continue;
            }
            $position = mb_stripos($line, $label);
            $inline = $position === false ? '' : trim(mb_substr($line, $position + mb_strlen($label)));
            if ($inline !== '') {
                $columns = preg_split('/\s{2,}/u', $inline) ?: [];
                $columns = array_values(array_filter($columns, fn (string $value): bool => (bool) preg_match('/^[+\-]?[\d\s.,]+$/u', trim($value))));
                if ($columns !== []) {
                    return $this->amount((string) end($columns));
                }
            }
            for ($next = $index + 1; $next < min(count($lines), $index + 4); $next++) {
                $value = trim($lines[$next]);
                if ($this->isAmountLine($value)) {
                    $columns = preg_split('/\s{2,}/u', $value) ?: [];

                    return $this->amount((string) end($columns));
                }
            }
        }

        return null;
    }

    private function amount(string $value): int
    {
        $value = str_replace([' ', "\u{00A0}", '.'], '', $value);

        return (int) str_replace(',', '.', $value);
    }

    /** @return array<int,int> */
    private function amountColumns(string $value): array
    {
        preg_match_all('/[+-]?\d{1,3}(?:[ \x{00a0}]\d{3})+|[+-]?\d+/u', $value, $matches);

        return array_map(fn (string $number): int => $this->amount($number), $matches[0]);
    }

    /** @param array<int,int> $row */
    private function rowTotal(array $row): ?int
    {
        if ($row === []) {
            return null;
        }

        return (int) end($row);
    }
}
