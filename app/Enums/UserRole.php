<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case ReadWrite = 'read-write';
    case ReadOnly = 'read-only';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
