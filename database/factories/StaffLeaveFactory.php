<?php

namespace Database\Factories;

use App\Enums\LeaveType;
use App\Models\StaffLeave;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffLeave>
 */
class StaffLeaveFactory extends Factory
{
    public function definition(): array
    {
        $first = today()->addDays(fake()->numberBetween(1, 30));

        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(LeaveType::cases()),
            'first_day' => $first->toDateString(),
            'return_date' => $first->copy()->addDays(fake()->numberBetween(1, 10))->toDateString(),
            'note' => null,
            'recorded_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
        ];
    }

    /** Leave running from $first up to, but not including, $returnDate. */
    public function between(string $first, string $returnDate): static
    {
        return $this->state(fn () => [
            'first_day' => $first,
            'return_date' => $returnDate,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'cancelled_at' => now(),
        ]);
    }

    public function ofType(LeaveType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }
}
