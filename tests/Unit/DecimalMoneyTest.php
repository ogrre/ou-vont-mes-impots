<?php

namespace Tests\Unit;

use App\Support\DecimalMoney;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DecimalMoneyTest extends TestCase
{
    #[DataProvider('additions')]
    public function test_it_adds_signed_decimal_amounts_exactly(string $left, string $right, string $expected): void
    {
        $this->assertSame($expected, DecimalMoney::add($left, $right));
    }

    public static function additions(): array
    {
        return [
            ['99999999999999999999.99', '0.01', '100000000000000000000.00'],
            ['10.00', '-3.25', '6.75'],
            ['3.25', '-10.00', '-6.75'],
            ['-3.25', '-6.75', '-10.00'],
            ['5.00', '-5.00', '0.00'],
            ['0.00', '-0.00', '0.00'],
            ['0001.02', '0000.03', '1.05'],
            ['-0001.02', '1.02', '0.00'],
        ];
    }

    public function test_it_sums_and_compares_amounts(): void
    {
        $this->assertSame('8.50', DecimalMoney::sum(['10.00', '-2.00', '0.50']));
        $this->assertSame(1, DecimalMoney::compare('10.00', '2.00'));
        $this->assertSame(-1, DecimalMoney::compare('-10.00', '2.00'));
        $this->assertSame(-1, DecimalMoney::compare('-10.00', '-2.00'));
        $this->assertSame(0, DecimalMoney::compare('2.00', '2.00'));
        $this->assertSame(0, DecimalMoney::compare('002.00', '2.00'));
        $this->assertSame(1, DecimalMoney::compare('-2.00', '-10.00'));
    }

    public function test_it_rejects_amounts_without_exactly_two_decimal_places(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DecimalMoney::add('1', '2.00');
    }

    #[DataProvider('invalidAmounts')]
    public function test_it_rejects_malformed_amounts(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        DecimalMoney::compare($amount, '0.00');
    }

    /** @return array<string, array{string}> */
    public static function invalidAmounts(): array
    {
        return [
            'missing integer part' => ['.10'],
            'one decimal' => ['1.0'],
            'three decimals' => ['1.000'],
            'plus sign' => ['+1.00'],
            'spaces' => ['1. 00'],
        ];
    }
}
