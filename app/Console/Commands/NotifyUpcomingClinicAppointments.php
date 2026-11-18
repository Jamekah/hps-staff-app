<?php

namespace App\Console\Commands;

use App\Models\ClinicAppointment;
use App\Notifications\ClinicAppointmentReminder;
use App\Services\StaffAvailability;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class NotifyUpcomingClinicAppointments extends Command
{
    protected $signature = 'clinic:notify-upcoming';

    protected $description = 'Remind clinicians 60 minutes before an appointment starts (runs every 5 minutes)';

    public function handle(StaffAvailability $availability): int
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

            if (! $clinician || ! $clinician->isNotifiable()) {
                continue;
            }

            // On leave when the appointment starts: no reminder. The booking
            // itself still shows as a clash on the Availability page.
            if ($availability->leaveFor($clinician->id, $appointment->starts_at, $appointment->starts_at->copy()->addMinute())) {
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
