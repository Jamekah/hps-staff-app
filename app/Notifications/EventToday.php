<?php

namespace App\Notifications;

use App\Models\Event;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class EventToday extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Event $event)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    protected function payload(): array
    {
        return [
            'title' => "Event today: {$this->event->name}",
            'body' => 'Starts at '.$this->event->starts_at->format('g:ia')
                .($this->event->location ? " — {$this->event->location}" : ''),
            'link' => route('calendar', absolute: false),
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
