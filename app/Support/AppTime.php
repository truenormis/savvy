<?php

namespace App\Support;

use DateTimeZone;
use Illuminate\Support\Carbon;
use Throwable;

class AppTime
{
    protected static ?array $identifiers = null;

    public static function identifiers(): array
    {
        return self::$identifiers ??= array_values(array_filter(
            timezone_identifiers_list(DateTimeZone::ALL_WITH_BC),
            function (string $name) {
                try {
                    new DateTimeZone($name);

                    return true;
                } catch (Throwable) {
                    return false;
                }
            }
        ));
    }

    public static function available(): array
    {
        $canonical = array_flip(timezone_identifiers_list());
        $now = Carbon::now('UTC');

        return array_map(fn (string $name) => [
            'name' => $name,
            'offset' => $now->copy()->setTimezone($name)->format('P'),
            'canonical' => isset($canonical[$name]),
        ], self::identifiers());
    }

    public static function timezone(): string
    {
        $fallback = (string) config('app.timezone');

        try {
            $configured = settings('timezone');
        } catch (Throwable) {
            return $fallback;
        }

        if (is_string($configured) && in_array($configured, self::identifiers(), true)) {
            return $configured;
        }

        return $fallback;
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    public static function today(): string
    {
        return self::now()->toDateString();
    }
}
