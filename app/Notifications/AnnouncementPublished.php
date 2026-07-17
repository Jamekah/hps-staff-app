<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AnnouncementPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Announcement $announcement)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    protected function payload(): array
    {
        return [
            'title' => $this->announcement->title,
            'body' => Str::limit($this->announcement->body, 120),
            'link' => route('announcements', absolute: false),
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
