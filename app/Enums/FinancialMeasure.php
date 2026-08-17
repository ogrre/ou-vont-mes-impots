<?php

namespace App\Enums;

enum FinancialMeasure: string
{
    case CommitmentAuthorization = 'commitment_authorization';
    case PaymentCredit = 'payment_credit';

    public function officialLabel(): string
    {
        return match ($this) {
            self::CommitmentAuthorization => 'AE',
            self::PaymentCredit => 'CP',
        };
    }
}
