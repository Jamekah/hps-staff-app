<?php

namespace App\Notifications;

use App\Models\GymSchedule;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class GymSessionReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public GymSchedule $schedule)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    protected function payload(): array
    {
        $start = substr($this->schedule->start_time, 0, 5);
        $end = substr($this->schedule->end_time, 0, 5);

        return [
            'title' => "Gym session in 1 hour: {$this->schedule->name}",
            'body' => "Studio {$this->schedule->studio}, {$start}–{$end} — {$this->schedule->client_name}",
            'link' => route('gym', absolute: false),
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
