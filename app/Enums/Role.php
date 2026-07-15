<?php

namespace App\Enums;

enum Role: string
{
    case Staff = 'staff';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Admin => 'Admin',
            self::SuperAdmin => 'Super Admin',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
