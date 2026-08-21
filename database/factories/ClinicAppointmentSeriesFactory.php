<?php

namespace Database\Factories;

use App\Models\ClinicAppointmentSeries;
use App\Models\ClinicClient;
use App\Models\ClinicService;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicAppointmentSeries>
 */
class ClinicAppointmentSeriesFactory extends Factory
{
    protected $model = ClinicAppointmentSeries::class;

    public function definition(): array
    {
        return [
            'clinic_client_id' => ClinicClient::factory(),
            'clinic_service_id' => ClinicService::factory()->recurring(),
            'assigned_staff_id' => User::factory()->clinician(),
            'booked_by' => User::factory()->admin(),
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'recurrence' => 'weekly',
            'days_of_week' => [1, 3, 5],
            'start_date' => today(),
            'end_date' => today()->addWeeks(4),
            'note' => null,
        ];
    }

    public function daily(): static
    {
        return $this->state(fn () => [
            'recurrence' => 'daily',
            'days_of_week' => null,
        ]);
    }

    /** @param  array<int>  $days  0 = Sunday … 6 = Saturday */
    public function weeklyOn(array $days): static
    {
        return $this->state(fn () => [
            'recurrence' => 'weekly',
            'days_of_week' => $days,
        ]);
    }
}
