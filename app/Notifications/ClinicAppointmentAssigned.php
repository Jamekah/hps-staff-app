<?php

namespace App\Notifications;

use App\Models\ClinicAppointment;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells a clinician they've been given an appointment. A recurring series
 * sends ONE summary notice (its first occurrence plus a count), not one per
 * materialised occurrence.
 */
class ClinicAppointmentAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ClinicAppointment $appointment,
        public ?int $sessionCount = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    protected function payload(): array
    {
        $client = $this->appointment->client?->name ?? 'a client';
        $service = $this->appointment->service?->name ?? 'appointment';
        $when = $this->appointment->starts_at->format('D j M, g:ia');

        $body = $this->sessionCount && $this->sessionCount > 1
            ? "{$client} — {$service}. {$this->sessionCount} sessions from {$when}."
            : "{$client} — {$service}, {$when}.";

        return [
            'title' => 'New appointment assigned',
            'body' => $body,
            'link' => route('clinic.mine', absolute: false),
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toFcm(object $notifiable): array
    {
        return $this->payload();
    }
}
