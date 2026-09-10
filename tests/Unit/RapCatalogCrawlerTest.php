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
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'docuement_dossier%5B0%5D=typologie%3A115') && str_contains($request->url(), 'page=0'));
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

    public function test_it_stops_at_the_first_empty_page_after_page_zero(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push('<article>2024 RAP 101 - Test <a href="/test.pdf">Télécharger PDF</a></article>')
            ->push('<html></html>')]);

        app(RapCatalogCrawler::class)->discover();

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'page=1'));
    }

    public function test_it_accepts_accented_context_and_absolute_urls(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push('<div>RAP 2024 101 – Éducation <a href="HTTP://example.test/report.pdf">Télécharger le PDF</a></div>')
            ->push('<html></html>')]);

        $entries = app(RapCatalogCrawler::class)->discover();

        $this->assertSame('101', $entries[0]['program']);
        $this->assertSame('HTTP://example.test/report.pdf', $entries[0]['url']);
    }

    public function test_it_normalizes_context_and_resolves_relative_urls(): void
    {
        $crawler = app(RapCatalogCrawler::class);
        $invoke = static function (string $method, mixed ...$arguments) use ($crawler): mixed {
            $reflection = new \ReflectionMethod($crawler, $method);
            $reflection->setAccessible(true);

            return $reflection->invoke($crawler, ...$arguments);
        };

        $this->assertSame('RAP 2024 A 101 - Test', $invoke('normalizeContext', 'RAP2024A101 - Test'));
        $this->assertSame('https://example.test/a.pdf', $invoke('absoluteUrl', 'https://example.test/a.pdf'));
        $this->assertSame('https://www.budget.gouv.fr/a.pdf', $invoke('absoluteUrl', '/a.pdf'));
        $this->assertSame('https://www.budget.gouv.fr/a.pdf', $invoke('absoluteUrl', 'a.pdf'));
    }

    public function test_it_honours_the_fifty_page_safety_limit(): void
    {
        $sequence = Http::sequence();
        for ($page = 0; $page < 50; $page++) {
            $sequence->push('<article>RAP 2024 101 - Test '.$page.' <a href="/test.pdf">Télécharger PDF</a></article>');
        }
        $sequence->push('<html></html>');
        Http::fake(['*' => $sequence]);

        app(RapCatalogCrawler::class)->discover();

        Http::assertSentCount(50);
    }
}
