<?php

namespace App\Enums;

enum ObservationStatus: string
{
    case Executed = 'executed';
    case InitialEstimate = 'initial_estimate';
    case RevisedEstimate = 'revised_estimate';
    case BudgetBill = 'budget_bill';
}
