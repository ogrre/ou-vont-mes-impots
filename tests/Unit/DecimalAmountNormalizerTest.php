<?php

namespace Tests\Unit;

use App\Services\Imports\DecimalAmountNormalizer;
use App\Services\Imports\Exceptions\InvalidSourceDataException;
use PHPUnit\Framework\TestCase;

class DecimalAmountNormalizerTest extends TestCase
{
    public function test_it_converts_billions_to_euros_without_float_arithmetic(): void
    {
        $normalizer = new DecimalAmountNormalizer;

        $this->assertSame('17553562690.69', $normalizer->billionEurToEur('17.553562690689997'));
        $this->assertSame('1000000000.00', $normalizer->billionEurToEur('1'));
        $this->assertSame('-132516931.90', $normalizer->billionEurToEur('-0.1325169319'));
    }

    public function test_it_handles_rounding_commas_leading_zeroes_and_negative_zero(): void
    {
        $normalizer = new DecimalAmountNormalizer;

        $this->assertSame('123456789.01', $normalizer->billionEurToEur('000.123456789006'));
        $this->assertSame('1500000000.00', $normalizer->billionEurToEur('1,5'));
        $this->assertSame('1000000000.00', $normalizer->billionEurToEur('0.999999999999'));
        $this->assertSame('0.00', $normalizer->billionEurToEur('-0'));
    }

    public function test_it_rejects_non_decimal_input(): void
    {
        $this->expectException(InvalidSourceDataException::class);
        (new DecimalAmountNormalizer)->billionEurToEur('1 000');
    }
}
