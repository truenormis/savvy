<?php

namespace App\Enums;

enum ReportMetric: string
{
    case Sum = 'sum';
    case Count = 'count';
    case Average = 'average';
    case Min = 'min';
    case Max = 'max';
    case Median = 'median';
    case PercentOfTotal = 'percent_of_total';
    case PercentOfIncome = 'percent_of_income';
}
