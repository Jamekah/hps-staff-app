<?php

namespace App\Notifications;

use App\Models\ClinicAppointment;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Prompts the assigned clinician to record an outcome once an appointment has
 * elapsed while still marked scheduled. Sent once — the appointment is stamped
 * with status_prompt_sent_at so it is never re-prompted.
 */
class ClinicStatusPrompt extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ClinicAppointment $appointment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    protected function payload(): array
    {
        $client = $this->appointment->client?->name ?? 'the client';
        $service = $this->appointment->service?->name ?? 'appointment';

        return [
            'title' => "Set the outcome for {$client}'s {$service}",
            'body' => 'Mark it completed or a no-show.',
            'link' => route('clinic.mine', ['appointment' => $this->appointment->id], absolute: false),
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
