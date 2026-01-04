<?php

namespace App\Enums;

enum ReportGroupBy: string
{
    case Categories = 'categories';
    case Days = 'days';
    case Weeks = 'weeks';
    case Months = 'months';
    case Accounts = 'accounts';
    case Tags = 'tags';
    case None = 'none';
}
