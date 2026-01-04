<?php

namespace App\Enums;

enum ReportCompareWith: string
{
    case PreviousPeriod = 'previous_period';
    case SamePeriodLastYear = 'same_period_last_year';
    case Budget = 'budget';
}
