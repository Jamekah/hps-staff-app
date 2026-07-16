<?php

namespace App\Enums;

enum EventType: string
{
    case Internal = 'internal';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal',
            self::External => 'External',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
