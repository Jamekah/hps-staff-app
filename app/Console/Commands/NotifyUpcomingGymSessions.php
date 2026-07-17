<?php

namespace App\Console\Commands;

use App\Models\GymSchedule;
use App\Notifications\GymSessionReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class NotifyUpcomingGymSessions extends Command
{
    protected $signature = 'gym:notify-upcoming';

    protected $description = 'Remind allocated staff 60 minutes before a gym session starts (runs every 5 minutes)';

    public function handle(): int
    {
        $today = today();

        // Half-open window matched to the 5-minute cadence: each occurrence
        // falls into exactly one run.
        $windowStart = now()->addMinutes(60);
        $windowEnd = now()->addMinutes(65);

        $sessions = GymSchedule::occurrencesOn($today)
            ->filter(function (GymSchedule $session) use ($today, $windowStart, $windowEnd) {
                $startsAt = $today->copy()->setTimeFromTimeString($session->start_time);

                return $startsAt->gte($windowStart) && $startsAt->lt($windowEnd);
            });

        foreach ($sessions as $session) {
            $staff = $session->staff->where('is_active', true);

            if ($staff->isEmpty()) {
                continue;
            }

            // Safety dedupe against scheduler overlap/retries: Cache::add is
            // a no-op returning false when the key already exists.
            $dedupeKey = "gym-notified:{$session->id}:{$today->toDateString()}";

            if (! Cache::add($dedupeKey, true, now()->addDay())) {
                continue;
            }

            Notification::send($staff, new GymSessionReminder($session));

            $this->info("Notified {$staff->count()} staff: {$session->name}");
        }

        return self::SUCCESS;
    }
}
