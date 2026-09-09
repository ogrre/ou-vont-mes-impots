<?php

namespace App\Enums;

enum AccountingBasis: string
{
    case Budgetary = 'budgetary';
    case NationalAccounts = 'national_accounts';
    case SocialProtectionAccounts = 'social_protection_accounts';
    case HealthAccounts = 'health_accounts';
}
