<?php

namespace App\Enums;

enum BudgetStage: string
{
    case Forecast = 'forecast';
    case InitialBudget = 'initial_budget';
    case AmendedBudget = 'amended_budget';
    case Execution = 'execution';
}
