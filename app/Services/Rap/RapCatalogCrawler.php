<?php

namespace App\Services\Rap;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RapCatalogCrawler
{
    public const PAGE_URL = 'https://www.budget.gouv.fr/documentation/documents-budgetaires/exercice-2024/plrg-2024';

    /** @return array<int, array{program:string, name:string, url:string, page:int}> */
    public function discover(): array
    {
        $entries = [];

        for ($page = 0; $page < 50; $page++) {
            $url = self::PAGE_URL.'?'.http_build_query([
                'docuement_dossier' => ['0' => 'typologie:115'],
                'page' => $page,
            ]);
            $html = $this->get($url)->body();
            $foundOnPage = $this->parsePage($html, $page);

            foreach ($foundOnPage as $entry) {
                $entries[$entry['program']] = $entry;
            }

            if ($page > 0 && $foundOnPage === []) {
                break;
            }
        }

        ksort($entries, SORT_NATURAL);

        if ($entries === []) {
            throw new RuntimeException('La page officielle n’a fourni aucune entrée RAP. Réponse HTML possiblement bloquée ou structure modifiée.');
        }

        return array_values($entries);
    }

    /** @return array<int, array{program:string, name:string, url:string, page:int}> */
    private function parsePage(string $html, int $page): array
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $entries = [];

        foreach ($xpath->query('//a[@href]') ?: [] as $anchor) {
            if (! $anchor instanceof \DOMElement) {
                continue;
            }
            $label = trim(preg_replace('/\s+/u', ' ', $anchor->textContent) ?? '');
            if (! preg_match('/t[ée]l[ée]charger.*\bpdf\b/iu', $label)) {
                continue;
            }

            $context = $this->context($anchor);
            if (! preg_match('/\bRAP\b/iu', $context)) {
                continue;
            }

            if (! preg_match('/(?:^|\s)(\d{3})\s*[-–—]\s*(.+?)(?=\s+(?:Télécharger|$))/iu', $context, $match)) {
                continue;
            }

            $url = $this->absoluteUrl($anchor->getAttribute('href'));
            $entries[$match[1]] = [
                'program' => $match[1],
                'name' => trim($match[2]),
                'url' => $url,
                'page' => $page,
            ];
        }

        return array_values($entries);
    }

    private function context(\DOMElement $anchor): string
    {
        $node = $anchor;
        for ($i = 0; $i < 7 && $node instanceof \DOMElement; $i++, $node = $node->parentNode) {
            $text = $this->normalizeContext($node->textContent);
            if (preg_match('/\bRAP\b.*\b\d{3}\s*[-–—]/iu', $text)) {
                return $text;
            }
        }

        return $this->normalizeContext($anchor->parentNode->textContent);
    }

    private function normalizeContext(string $text): string
    {
        $text = preg_replace('/([\p{L}])(\d)/u', '$1 $2', $text) ?? $text;
        $text = preg_replace('/(\d)([\p{L}])/u', '$1 $2', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function absoluteUrl(string $href): string
    {
        if (preg_match('/^https?:\/\//i', $href)) {
            return $href;
        }

        return 'https://www.budget.gouv.fr/'.ltrim($href, '/');
    }

    private function get(string $url): Response
    {
        $response = Http::withHeaders([
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'fr-FR,fr;q=0.9',
            'User-Agent' => 'Mais-ou-vont-mes-impots/1.0 (+https://github.com/)',
        ])->timeout(60)->retry(3, 1000)->get($url);

        if ($response->failed()) {
            throw new RuntimeException("Échec de récupération de {$url} ({$response->status()}).");
        }

        return $response;
    }
}
