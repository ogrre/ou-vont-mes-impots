<?php

namespace App\Support;

use InvalidArgumentException;

final class DecimalMoney
{
    public static function add(string $left, string $right): string
    {
        [$leftNegative, $leftCents] = self::parse($left);
        [$rightNegative, $rightCents] = self::parse($right);

        if ($leftNegative === $rightNegative) {
            return self::format($leftNegative, self::unsignedAdd($leftCents, $rightCents));
        }

        $comparison = self::compareUnsigned($leftCents, $rightCents);

        if ($comparison === 0) {
            return '0.00';
        }

        if ($comparison > 0) {
            return self::format($leftNegative, self::unsignedSubtract($leftCents, $rightCents));
        }

        return self::format($rightNegative, self::unsignedSubtract($rightCents, $leftCents));
    }

    /** @param iterable<string> $amounts */
    public static function sum(iterable $amounts): string
    {
        $total = '0.00';

        foreach ($amounts as $amount) {
            $total = self::add($total, $amount);
        }

        return $total;
    }

    public static function compare(string $left, string $right): int
    {
        [$leftNegative, $leftCents] = self::parse($left);
        [$rightNegative, $rightCents] = self::parse($right);

        if ($leftNegative !== $rightNegative) {
            return $leftNegative ? -1 : 1;
        }

        $comparison = self::compareUnsigned($leftCents, $rightCents);

        return $leftNegative ? -$comparison : $comparison;
    }

    /** @return array{bool, string} */
    private static function parse(string $amount): array
    {
        if (! preg_match('/^(-?)(\d+)\.(\d{2})$/', $amount, $matches)) {
            throw new InvalidArgumentException("Montant monétaire invalide : {$amount}");
        }

        $cents = ltrim($matches[2].$matches[3], '0') ?: '0';

        return [$matches[1] === '-' && $cents !== '0', $cents];
    }

    private static function format(bool $negative, string $cents): string
    {
        $cents = str_pad(ltrim($cents, '0') ?: '0', 3, '0', STR_PAD_LEFT);
        $amount = substr($cents, 0, -2).'.'.substr($cents, -2);

        return $negative && $amount !== '0.00' ? '-'.$amount : $amount;
    }

    private static function compareUnsigned(string $left, string $right): int
    {
        $lengthComparison = strlen($left) <=> strlen($right);

        return $lengthComparison !== 0 ? $lengthComparison : strcmp($left, $right);
    }

    private static function unsignedAdd(string $left, string $right): string
    {
        $carry = 0;
        $result = '';
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;

        while ($leftIndex >= 0 || $rightIndex >= 0 || $carry > 0) {
            $sum = ($leftIndex >= 0 ? (int) $left[$leftIndex--] : 0)
                + ($rightIndex >= 0 ? (int) $right[$rightIndex--] : 0)
                + $carry;
            $result = ($sum % 10).$result;
            $carry = intdiv($sum, 10);
        }

        return $result;
    }

    /** Subtract two unsigned integer strings where $left >= $right. */
    private static function unsignedSubtract(string $left, string $right): string
    {
        $borrow = 0;
        $result = '';
        $rightIndex = strlen($right) - 1;

        for ($leftIndex = strlen($left) - 1; $leftIndex >= 0; $leftIndex--) {
            $digit = (int) $left[$leftIndex] - $borrow
                - ($rightIndex >= 0 ? (int) $right[$rightIndex--] : 0);

            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result = $digit.$result;
        }

        return ltrim($result, '0') ?: '0';
    }
}
