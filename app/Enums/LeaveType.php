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

    /**
     * Working days allowed per calendar year, or null when the type has no
     * limit (training, duty travel, other — counted and shown, never warned
     * about).
     *
     * Read from config/leave.php rather than hard-coded, so HR can change the
     * numbers without a code change and every count in the app agrees.
     */
    public function allowance(): ?int
    {
        $allowances = config('leave.allowances', []);

        return array_key_exists($this->value, $allowances)
            ? (int) $allowances[$this->value]
            : null;
    }

    public function isLimited(): bool
    {
        return $this->allowance() !== null;
    }

    /** The types that carry an allowance, in display order. */
    public static function limited(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type->isLimited()));
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
