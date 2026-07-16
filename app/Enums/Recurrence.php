<?php

namespace App\Enums;

enum Recurrence: string
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::None => 'One-off',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
