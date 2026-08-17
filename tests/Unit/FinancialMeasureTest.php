<?php

namespace Tests\Unit;

use App\Enums\FinancialMeasure;
use PHPUnit\Framework\TestCase;

class FinancialMeasureTest extends TestCase
{
    public function test_official_labels_are_explicit(): void
    {
        $this->assertSame('AE', FinancialMeasure::CommitmentAuthorization->officialLabel());
        $this->assertSame('CP', FinancialMeasure::PaymentCredit->officialLabel());
    }
}
