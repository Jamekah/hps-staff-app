<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
            self::NoShow => 'No show',
        };
    }

    /**
     * Only scheduled appointments occupy a clinician's time — cancelled,
     * completed and no-show rows never block a new booking.
     */
    public function blocksAvailability(): bool
    {
        return $this === self::Scheduled;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
