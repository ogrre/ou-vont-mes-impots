<?php

namespace App\Services\Imports;

use App\Services\Imports\Exceptions\InvalidSourceDataException;

class DecimalAmountNormalizer
{
    /** Convert a decimal number of billions of EUR to an EUR decimal with two places, without floats. */
    public function billionEurToEur(string $value): string
    {
        $value = trim(str_replace(',', '.', $value));

        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw new InvalidSourceDataException("Montant invalide : {$value}");
        }

        $sign = $matches[1];
        $integer = ltrim($matches[2], '0') ?: '0';
        $fraction = $matches[3] ?? '';
        $scaled = $integer.str_pad(substr($fraction, 0, 11), 11, '0');
        $roundDigit = (int) ($fraction[11] ?? '0');
        $scaled = ltrim($scaled, '0') ?: '0';

        if ($roundDigit >= 5) {
            $scaled = $this->increment($scaled);
        }

        $scaled = str_pad($scaled, 3, '0', STR_PAD_LEFT);

        $normalized = substr($scaled, 0, -2).'.'.substr($scaled, -2);

        return $sign === '-' && $normalized !== '0.00' ? '-'.$normalized : $normalized;
    }

    private function increment(string $number): string
    {
        $digits = str_split($number);

        for ($index = count($digits) - 1; $index >= 0; $index--) {
            if ($digits[$index] !== '9') {
                $digits[$index] = (string) ((int) $digits[$index] + 1);

                return implode('', $digits);
            }

            $digits[$index] = '0';
        }

        return '1'.implode('', $digits);
    }
}
