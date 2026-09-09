<?php

namespace Tests\Unit;

use App\Services\Rap\RapPdfExtractor;
use App\Services\Rap\RapTextParser;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('rapFixtures')]
    public function test_reference_rap_pdfs_are_parseable(string $filename, int $minimumActions): void
    {
        $path = base_path('data/2024/budget-etat/performance/'.$filename);
        if (! is_file($path) || trim((string) shell_exec('command -v pdftotext')) === '') {
            $this->markTestSkipped('Fixture PDF ou pdftotext indisponible dans cet environnement.');
        }
        $result = app(RapTextParser::class)->parse(app(RapPdfExtractor::class)->extract($path), 'fixture', 'Fixture');
        $this->assertGreaterThanOrEqual($minimumActions, $result['counts']['actions']);
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
