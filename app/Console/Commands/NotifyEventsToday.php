<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\User;
use App\Notifications\EventToday;
use App\Services\StaffAvailability;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyEventsToday extends Command
{
    protected $signature = 'events:notify-today';

    protected $description = 'Notify assigned staff of events starting today (runs daily at 08:00 Port Moresby time)';

    public function handle(StaffAvailability $availability): int
    {
        $events = Event::whereDate('starts_at', today())
            ->with(['staff' => fn ($query) => $query->notifiable()])
            ->get();

        foreach ($events as $event) {
            if ($event->staff->isEmpty()) {
                continue;
            }

            // Nobody is reminded about something they are on leave for.
            $onLeave = $availability->onLeaveMap(
                $event->staff->pluck('id')->all(),
                $event->starts_at,
                $event->starts_at->copy()->addMinute(),
            );

            $recipients = $event->staff->reject(fn (User $member) => $onLeave[$member->id] ?? false);

            if ($recipients->isEmpty()) {
                $this->info("Skipped {$event->name}: every assigned staff member is on leave.");

                continue;
            }

            Notification::send($recipients, new EventToday($event));

            $this->info("Notified {$recipients->count()} staff: {$event->name}");
        }

        $this->info("Done. {$events->count()} event(s) today.");

        return self::SUCCESS;
    }
}
