<?php

namespace App\Enums;

enum FinancialMeasure: string
{
    case CommitmentAuthorization = 'commitment_authorization';
    case PaymentCredit = 'payment_credit';
    case CreditsOpened = 'credits_opened';
    case ExpenditureRecorded = 'expenditure_recorded';
    case Allocation = 'allocation';

    public function officialLabel(): string
    {
        return match ($this) {
            self::CommitmentAuthorization => 'AE',
            self::PaymentCredit => 'CP',
            self::CreditsOpened => 'Crédits ouverts',
            self::ExpenditureRecorded => 'Dépenses constatées',
            self::Allocation => 'Dotation',
        };
    }
}
