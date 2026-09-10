<?php

namespace Tests\Unit;

use App\Services\Rap\RapPdfExtractor;
use App\Services\Rap\RapTextParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class RapTextParserTest extends TestCase
{
    public function test_it_parses_the_action_table_by_textual_anchor(): void
    {
        $text = <<<'TEXT'
2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS OUVERTS ET DES CRÉDITS CONSOMMÉS
2024 / AUTORISATIONS D'ENGAGEMENT
01 – Action de test                                      100       200       300       300
                                                           10        20        30
Total des AE prévues en LFI                              300
Total des AE consommées                                  30
2024 / CRÉDITS DE PAIEMENT
01 – Action de test                                      100       200       300       300
                                                           11        21        32
Total des CP prévus en LFI                              300
Total des CP consommés                                  32
2023 / PRÉSENTATION PAR ACTION
TEXT;

        $result = app(RapTextParser::class)->parse($text, '999', 'Programme test');

        $this->assertSame(300, $result['actions'][0]['ae_lfi']);
        $this->assertSame(30, $result['actions'][0]['ae_consumed']);
        $this->assertSame(300, $result['actions'][0]['cp_lfi']);
        $this->assertSame(32, $result['actions'][0]['cp_consumed']);
        $this->assertSame('01', $result['actions'][0]['code']);
        $this->assertSame('Action de test', $result['actions'][0]['label']);
        $this->assertSame('action', $result['actions'][0]['hierarchy_level']);
        $this->assertNull($result['actions'][0]['parent_action_code']);
        $this->assertTrue($result['actions'][0]['contributes_to_program_total']);
        $this->assertSame([100, 200, 300, 300, 10, 20, 30], $result['actions'][0]['amounts']);
        $this->assertSame([100, 200, 300, 300], $result['actions'][0]['amount_rows'][0]);
        $this->assertSame([10, 20, 30], $result['actions'][0]['amount_rows'][1]);
        $this->assertSame([], $result['actions'][0]['titles']);
        $this->assertFalse($result['actions'][0]['review_required']);
    }

    public function test_it_keeps_institutional_credits_separate_from_ae_and_cp(): void
    {
        $text = <<<'TEXT'
Intitulé de l’action   Dotation 2024   Crédits ouverts   Dépenses constatées
Sénat                  341 864 000    341 864 000       341 864 000
Total                  341 864 000    341 864 000       341 864 000
TEXT;

        $result = app(RapTextParser::class)->parse($text, '521', 'Sénat');
        $action = $result['actions'][0];

        $this->assertSame('institutional_credits', $result['format']);
        $this->assertSame(341864000, $action['special_measurements']['allocation']);
        $this->assertSame(341864000, $action['special_measurements']['credits_opened']);
        $this->assertSame(341864000, $action['special_measurements']['expenditure_recorded']);
        $this->assertNull($action['ae_lfi']);
        $this->assertNull($action['cp_lfi']);
        $this->assertSame('Sénat', $action['label']);
        $this->assertSame('action', $action['hierarchy_level']);
        $this->assertSame([], $action['amount_rows']);
        $this->assertSame([], $action['titles']);
        $this->assertFalse($action['review_required']);
    }

    public function test_it_handles_the_four_column_institutional_format_without_conversion(): void
    {
        $text = <<<'TEXT'
Intitulé de l’action   Dotation prévue en LFI   Dotation supplémentaire   Total des crédits ouverts   Dépenses constatées
Assemblée nationale    607 647 569              19 534 273                627 181 842                 627 181 842
Total                   607 647 569              19 534 273                627 181 842                 627 181 842
TEXT;

        $result = app(RapTextParser::class)->parse($text, '511', 'Assemblée nationale');

        $this->assertSame(607647569, $result['actions'][0]['special_measurements']['allocation']);
        $this->assertSame(627181842, $result['actions'][0]['special_measurements']['credits_opened']);
        $this->assertSame(627181842, $result['actions'][0]['special_measurements']['expenditure_recorded']);
    }

    public function test_it_reads_multicolumn_totals_after_the_label_and_detects_mismatches(): void
    {
        $text = <<<'TEXT'
2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS OUVERTS ET DES CRÉDITS CONSOMMÉS
2024 / AUTORISATIONS D'ENGAGEMENT
01 – Action de test                                      1       2       3       4       5       6
                                                           1       2       3       4       5       6
TOTAL DES AE PRÉVUES EN LFI
                                                           6       2007
TOTAL DES AE CONSOMMÉES                                  6
2024 / CRÉDITS DE PAIEMENT
01 – Action de test                                      1       2       3       4       5       6
                                                           1       2       3       4       5       6
TOTAL DES CP PRÉVUS EN LFI                               6
TOTAL DES CP CONSOMMÉS
                                                           6       2007
2023 / PRÉSENTATION PAR ACTION
TEXT;

        $result = app(RapTextParser::class)->parse($text, '999', 'Programme test');

        $this->assertSame(2007, $result['validation']['totals']['ae_lfi']);
        $this->assertSame(6, $result['validation']['totals']['ae_consumed']);
        $this->assertSame(6, $result['validation']['totals']['cp_lfi']);
        $this->assertSame(2007, $result['validation']['totals']['cp_consumed']);
        $this->assertSame(1000, $result['validation']['tolerance_eur']);
        $this->assertTrue($result['validation']['review_required']);
        $this->assertSame(6, $result['actions'][0]['ae_lfi']);
        $this->assertSame(6, $result['actions'][0]['cp_consumed']);
        $this->assertArrayHasKey('ae_lfi', $result['validation']['differences']);
        $this->assertArrayHasKey('cp_consumed', $result['validation']['differences']);
        $this->assertSame(6, $result['validation']['differences']['ae_lfi']['actions_sum']);
        $this->assertSame(2007, $result['validation']['differences']['ae_lfi']['programme_total']);
        $this->assertSame(-2001, $result['validation']['differences']['ae_lfi']['difference']);
    }

    public function test_it_rejects_an_anchor_without_a_numbered_action(): void
    {
        $text = '2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS OUVERTS ET DES CRÉDITS CONSOMMÉS';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aucune action chiffrée');

        app(RapTextParser::class)->parse($text, '999', 'Programme test');
    }

    public function test_it_parses_special_institution_rows_and_ignores_non_action_lines(): void
    {
        $text = <<<'TEXT'
Intitulé de l’action   Dotation 2024   Crédits ouverts   Dépenses constatées
Intitulé  sans chiffres
Sénat                  341 864 000    341 864 000       341 864 000
Assemblée nationale    607 647 569    19 534 273        627 181 842       627 181 842
Total                  948 000 000    948 000 000       948 000 000
TEXT;

        $result = app(RapTextParser::class)->parse($text, '511', 'Institution');

        $this->assertSame(['1', '2'], array_column($result['actions'], 'code'));
        $this->assertSame([341864000, 341864000, 341864000], $result['actions'][0]['amounts']);
        $this->assertSame([607647569, 627181842, 627181842], $result['actions'][1]['amounts']);
        $this->assertFalse($result['actions'][0]['contributes_to_program_total']);
    }

    public function test_it_rejects_an_empty_special_institution_table(): void
    {
        $text = <<<'TEXT'
Dotation 2024   Crédits ouverts   Dépenses constatées
Total                  100          100          100
TEXT;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Section 2024 par action introuvable.');

        app(RapTextParser::class)->parse($text, '511', 'Institution');
    }

    public function test_it_does_not_add_sub_actions_to_programme_totals(): void
    {
        $text = <<<'TEXT'
2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS OUVERTS ET DES CRÉDITS CONSOMMÉS
2024 / AUTORISATIONS D'ENGAGEMENT
01 – Parent                                      100       200       300       300
01.1 – Enfant                                    900       900       900       900
Total des AE prévues en LFI                      300
2024 / CRÉDITS DE PAIEMENT
01 – Parent                                      100       200       300       300
01.1 – Enfant                                    900       900       900       900
Total des CP prévus en LFI                       300
TEXT;

        $result = app(RapTextParser::class)->parse($text, '999', 'Programme test');

        $this->assertFalse($result['validation']['review_required']);
        $this->assertTrue($result['actions'][0]['contributes_to_program_total']);
        $this->assertFalse($result['actions'][1]['contributes_to_program_total']);
        $this->assertSame('sub_action', $result['actions'][1]['hierarchy_level']);
        $this->assertSame('01', $result['actions'][1]['parent_action_code']);
    }

    public function test_it_handles_wrapped_labels_missing_totals_and_numeric_formats(): void
    {
        $text = <<<'TEXT'
2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS OUVERTS ET DES CRÉDITS CONSOMMÉS
2024 / AUTORISATIONS D'ENGAGEMENT
02 – Action avec libellé
-5       1234       6
02.1 – Action suivante                            10         20
2024 / CRÉDITS DE PAIEMENT
02 – Action avec libellé                         -5       1234       6
TEXT;

        $result = app(RapTextParser::class)->parse($text, '999', 'Programme test');

        $amounts = array_merge(...array_column($result['actions'], 'amounts'));
        $this->assertContains(1234, $amounts);
        $this->assertContains(-5, $amounts);
        $this->assertContains(6, $amounts);
        $this->assertNull($result['validation']['totals']['ae_lfi']);
        $this->assertTrue($result['actions'][0]['review_required']);
    }

    public function test_it_validates_the_parser_primitives_and_fallbacks(): void
    {
        $parser = app(RapTextParser::class);
        $invoke = static function (string $method, mixed ...$arguments) use ($parser): mixed {
            $reflection = new \ReflectionMethod($parser, $method);
            $reflection->setAccessible(true);

            return $reflection->invoke($parser, ...$arguments);
        };

        $this->assertTrue($invoke('isAmountLine', '- 1 234,50'));
        $this->assertFalse($invoke('isAmountLine', 'un texte'));
        $this->assertSame(['Libellé', '10   -2'], $invoke('splitLabelColumns', 'Libellé     10   -2'));
        $this->assertSame(['Libellé sans colonnes', null], $invoke('splitLabelColumns', 'Libellé sans colonnes'));
        $this->assertSame(1234, $invoke('amount', "1\u{00a0}234,50"));
        $this->assertSame([1234, -2, 3], $invoke('amountColumns', '+1 234       -2       3'));
        $this->assertNull($invoke('rowTotal', []));
        $this->assertSame(2007, $invoke('totalAfter', ['Total des recettes  6  2007'], 'Total des recettes'));
        $this->assertSame(2007, $invoke('totalAfter', ['Total des recettes', '6  2007'], 'Total des recettes'));
        $this->assertNull($invoke('totalAfter', ['Autre ligne'], 'Total des recettes'));

        $validation = $invoke('validateTotals', "Total des AE prévues en LFI  100\nTotal des AE consommées  100", [
            ['ae_lfi' => 100, 'ae_consumed' => 100, 'contributes_to_program_total' => true],
            ['ae_lfi' => 9999, 'ae_consumed' => 9999, 'contributes_to_program_total' => false],
        ]);
        $this->assertSame(100, $validation['totals']['ae_lfi']);
        $this->assertSame(100, $validation['totals']['ae_consumed']);
        $this->assertSame([], $validation['differences']);
        $this->assertFalse($validation['review_required']);
    }

    #[Group('slow')]
    #[DataProvider('rapFixtures')]
    public function test_reference_rap_pdfs_are_parseable(string $filename, int $minimumActions): void
    {
        $path = base_path('data/2024/budget-etat/performance/'.$filename);
        if (! is_file($path) || trim((string) shell_exec('command -v pdftotext')) === '') {
            $this->markTestSkipped('Fixture PDF ou pdftotext indisponible dans cet environnement.');
        }
        $result = app(RapTextParser::class)->parse(app(RapPdfExtractor::class)->extract($path), 'fixture', 'Fixture');
        $this->assertGreaterThanOrEqual($minimumActions, $result['counts']['actions']);
        $this->assertSame(2024, $result['year']);
        $this->assertSame(['code' => 'fixture', 'name' => 'Fixture'], $result['program']);
        $this->assertSame('2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS', $result['parser']['anchor']);
        $this->assertSame('layout-column-order-v1', $result['parser']['amount_mapping']);
        $this->assertSame($result['counts']['actions'], count($result['actions']));
        $this->assertArrayHasKey('warnings', $result);

        foreach ($result['actions'] as $action) {
            $this->assertArrayHasKey('code', $action);
            $this->assertArrayHasKey('label', $action);
            $this->assertArrayHasKey('hierarchy_level', $action);
            $this->assertArrayHasKey('parent_action_code', $action);
            $this->assertArrayHasKey('contributes_to_program_total', $action);
            $this->assertNotEmpty($action['amounts']);
            $this->assertArrayHasKey('amount_rows', $action);
            $this->assertArrayHasKey('extracted_lines', $action);
            $this->assertArrayHasKey('review_required', $action);
        }
    }

    /** @return array<string,array{string,int}> */
    public static function rapFixtures(): array
    {
        return [
            '101' => ['FR_2024_PLR_JA_PGM_101.pdf', 5],
            '102' => ['FR_2024_PLR_TB_PGM_102.pdf', 10],
            '104' => ['FR_2024_PLR_IA_PGM_104.pdf', 4],
        ];
    }
}
