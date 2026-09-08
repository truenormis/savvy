<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DateBoundary
{
    public static function start(string|CarbonInterface|null $date): ?string
    {
        return self::parse($date)?->startOfDay()->toDateTimeString();
    }

    public static function end(string|CarbonInterface|null $date): ?string
    {
        return self::parse($date)?->endOfDay()->toDateTimeString();
    }

    private static function parse(string|CarbonInterface|null $date): ?Carbon
    {
        if ($date === null || $date === '') {
            return null;
        }

        if ($date instanceof CarbonInterface) {
            return Carbon::instance($date->toDateTime());
        }

        return Carbon::parse($date);
    }
}
