<?php

namespace Tests\Unit;

use App\Services\Rap\RapPdfExtractor;
use RuntimeException;
use Tests\TestCase;

class RapPdfExtractorTest extends TestCase
{
    public function test_it_rejects_a_missing_or_unreadable_pdf(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PDF RAP introuvable ou illisible');

        app(RapPdfExtractor::class)->extract(base_path('data/does-not-exist.pdf'));
    }
}
