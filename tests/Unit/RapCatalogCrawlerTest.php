<?php

namespace Tests\Unit;

use App\Services\Rap\RapCatalogCrawler;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RapCatalogCrawlerTest extends TestCase
{
    public function test_it_uses_real_pdf_links_and_programme_labels_from_each_page(): void
    {
        $entry = fn (string $program, string $name, int $id): string => '<article>2024 PLRG RAP '.$program.' - '.$name.' <a href="/documentation/file-download/'.$id.'">Télécharger (pdf 1 Mo)</a></article>';
        Http::fake(['*' => Http::sequence()
            ->push($entry('101', 'Accès au droit et à la justice', 1))
            ->push($entry('102', "Accès et retour à l'emploi", 2))
            ->push('<html></html>')]);

        $entries = app(RapCatalogCrawler::class)->discover();

        $this->assertSame(['101', '102'], array_column($entries, 'program'));
        $this->assertSame('https://www.budget.gouv.fr/documentation/file-download/1', $entries[0]['url']);
        $this->assertSame("Accès et retour à l'emploi", $entries[1]['name']);
    }
}
