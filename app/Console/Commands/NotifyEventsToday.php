<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Notifications\EventToday;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyEventsToday extends Command
{
    protected $signature = 'events:notify-today';

    protected $description = 'Notify assigned staff of events starting today (runs daily at 08:00 Port Moresby time)';

    public function handle(): int
    {
        $events = Event::whereDate('starts_at', today())
            ->with(['staff' => fn ($query) => $query->where('is_active', true)])
            ->get();

        foreach ($events as $event) {
            if ($event->staff->isEmpty()) {
                continue;
            }

            Notification::send($event->staff, new EventToday($event));

            $this->info("Notified {$event->staff->count()} staff: {$event->name}");
        }

        $this->info("Done. {$events->count()} event(s) today.");

        return self::SUCCESS;
    }
}
