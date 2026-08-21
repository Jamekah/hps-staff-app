<?php

namespace App\Notifications;

use App\Models\ClinicAppointment;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Sent to the assigned clinician 60 minutes before an appointment starts. */
class ClinicAppointmentReminder extends Notification implements ShouldQueue
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
        $client = $this->appointment->client?->name ?? 'Client';
        $service = $this->appointment->service?->name ?? 'appointment';
        $start = $this->appointment->starts_at->format('g:ia');
        $end = $this->appointment->ends_at->format('g:ia');

        return [
            'title' => "Appointment in 1 hour: {$client}",
            'body' => "{$service}, {$start}–{$end}",
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
