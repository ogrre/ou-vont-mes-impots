<?php

namespace App\Enums;

enum MeasurementType: string
{
    case Expenditure = 'expenditure';
    case Revenue = 'revenue';
    case Tax = 'tax';
    case SocialContribution = 'social_contribution';
    case Deficit = 'deficit';
    case Debt = 'debt';
}
