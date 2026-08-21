<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\ClinicAppointment;
use App\Models\ClinicClient;
use App\Models\ClinicService;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicAppointment>
 */
class ClinicAppointmentFactory extends Factory
{
    protected $model = ClinicAppointment::class;

    public function definition(): array
    {
        $start = today()->setTime(9, 0);

        return [
            'clinic_client_id' => ClinicClient::factory(),
            'clinic_service_id' => ClinicService::factory(),
            'assigned_staff_id' => User::factory()->clinician(),
            'booked_by' => User::factory()->admin(),
            'series_id' => null,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(30),
            'note' => null,
            'status' => AppointmentStatus::Scheduled,
            'has_clash' => false,
            'status_prompt_sent_at' => null,
        ];
    }

    /** Place the appointment in a specific window. */
    public function at(\Carbon\CarbonInterface $start, int $minutes = 30): static
    {
        return $this->state(fn () => [
            'starts_at' => $start->copy(),
            'ends_at' => $start->copy()->addMinutes($minutes),
        ]);
    }

    public function status(AppointmentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function cancelled(): static
    {
        return $this->status(AppointmentStatus::Cancelled);
    }

    public function completed(): static
    {
        return $this->status(AppointmentStatus::Completed);
    }

    public function noShow(): static
    {
        return $this->status(AppointmentStatus::NoShow);
    }

    public function clashing(): static
    {
        return $this->state(fn () => ['has_clash' => true]);
    }
}
