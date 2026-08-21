<?php

namespace App\Console\Commands;

use App\Models\ClinicAppointment;
use App\Notifications\ClinicStatusPrompt;
use Illuminate\Console\Command;

class PromptClinicAppointmentStatus extends Command
{
    protected $signature = 'clinic:prompt-status';

    protected $description = 'Ask clinicians to record an outcome for appointments that have finished (runs every 15 minutes)';

    public function handle(): int
    {
        $appointments = ClinicAppointment::query()
            ->blocking()
            ->where('ends_at', '<', now())
            ->whereNull('status_prompt_sent_at')
            ->with(['client', 'service', 'clinician'])
            ->get();

        foreach ($appointments as $appointment) {
            $clinician = $appointment->clinician;

            if (! $clinician || ! $clinician->is_active) {
                continue;
            }

            $clinician->notify(new ClinicStatusPrompt($appointment));

            // Stamp regardless of delivery so a clinician is never re-prompted
            // for the same appointment.
            $appointment->forceFill(['status_prompt_sent_at' => now()])->save();

            $this->info("Prompted {$clinician->name} for appointment #{$appointment->id}");
        }

        return self::SUCCESS;
    }
}
