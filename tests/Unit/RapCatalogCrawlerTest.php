<?php

namespace Tests\Unit;

use App\Services\Rap\RapCatalogCrawler;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
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

    public function test_it_replaces_duplicate_programs_and_stops_after_an_empty_page(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push('<article>2024 PLRG RAP 101 - Première version <a href="https://example.test/first.pdf">Télécharger PDF</a></article>')
            ->push('<article>2024 PLRG RAP 101 - Version finale <a href="/final.pdf">Télécharger PDF</a></article>')
            ->push('<html></html>')]);

        $entries = app(RapCatalogCrawler::class)->discover();

        $this->assertCount(1, $entries);
        $this->assertSame('101', $entries[0]['program']);
        $this->assertSame('Version finale', $entries[0]['name']);
        $this->assertSame('https://www.budget.gouv.fr/final.pdf', $entries[0]['url']);
        $this->assertSame(1, $entries[0]['page']);
    }

    public function test_it_ignores_links_without_a_valid_rap_context(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push('<a href="/not-a-rap.pdf">Télécharger PDF</a>')
            ->push('<article>2024 PLRG RAP sans numéro <a href="/invalid.pdf">Télécharger PDF</a></article>')
            ->push('<html></html>')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('aucune entrée RAP');

        app(RapCatalogCrawler::class)->discover();
    }

    public function test_it_raises_an_error_when_the_catalog_request_fails(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('HTTP request returned status code 503');

        app(RapCatalogCrawler::class)->discover();
    }
}
