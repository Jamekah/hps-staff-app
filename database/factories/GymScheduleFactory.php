<?php

namespace Database\Factories;

use App\Enums\ClientType;
use App\Enums\Recurrence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\GymSchedule>
 */
class GymScheduleFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('now', '+2 weeks')->format('Y-m-d');

        return [
            'name' => fake()->sentence(2),
            'client_type' => fake()->randomElement(ClientType::cases()),
            'client_name' => fake()->company(),
            'studio' => fake()->randomElement(['1', '2']),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
            'recurrence' => Recurrence::None,
            'days_of_week' => null,
            'created_by' => User::factory()->admin(),
        ];
    }

    public function daily(string $startDate, string $endDate): static
    {
        return $this->state(fn () => [
            'recurrence' => Recurrence::Daily,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    public function weekly(string $startDate, string $endDate, array $daysOfWeek): static
    {
        return $this->state(fn () => [
            'recurrence' => Recurrence::Weekly,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days_of_week' => $daysOfWeek,
        ]);
    }
}
