<?php

namespace App\Console\Commands;

use App\Models\ClinicAppointment;
use App\Notifications\ClinicAppointmentReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class NotifyUpcomingClinicAppointments extends Command
{
    protected $signature = 'clinic:notify-upcoming';

    protected $description = 'Remind clinicians 60 minutes before an appointment starts (runs every 5 minutes)';

    public function handle(): int
    {
        // Half-open window matched to the 5-minute cadence, mirroring the gym
        // reminder: each appointment falls into exactly one run.
        $windowStart = now()->addMinutes(60);
        $windowEnd = now()->addMinutes(65);

        $appointments = ClinicAppointment::query()
            ->blocking()
            ->where('starts_at', '>=', $windowStart)
            ->where('starts_at', '<', $windowEnd)
            ->with(['client', 'service', 'clinician'])
            ->get();

        foreach ($appointments as $appointment) {
            $clinician = $appointment->clinician;

            if (! $clinician || ! $clinician->is_active) {
                continue;
            }

            // Safety dedupe against scheduler overlap/retries.
            if (! Cache::add("clinic-reminder:{$appointment->id}", true, now()->addDay())) {
                continue;
            }

            $clinician->notify(new ClinicAppointmentReminder($appointment));

            $this->info("Reminded {$clinician->name}: appointment #{$appointment->id}");
        }

        return self::SUCCESS;
    }
}
