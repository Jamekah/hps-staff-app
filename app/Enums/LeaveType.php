<?php

namespace App\Enums;

/**
 * Leave reasons. Deliberately shown only on the Staff Availability page —
 * everywhere else (pickers, badges, clinic labels) says "On leave" with no
 * reason, because clinic bookers and other staff shouldn't see why someone
 * is away.
 */
enum LeaveType: string
{
    case Annual = 'annual';
    case Sick = 'sick';
    case Training = 'training';
    case DutyTravel = 'duty_travel';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Annual => 'Annual leave',
            self::Sick => 'Sick leave',
            self::Training => 'Training / course',
            self::DutyTravel => 'Duty travel',
            self::Other => 'Other',
        };
    }

    /** Short form for chips, where the full label is too wide on a phone. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Annual => 'Annual',
            self::Sick => 'Sick',
            self::Training => 'Training',
            self::DutyTravel => 'Duty travel',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
