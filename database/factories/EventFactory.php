<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+1 month');

        return [
            'name' => fake()->sentence(3),
            'details' => fake()->paragraph(),
            'type' => fake()->randomElement(EventType::cases()),
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+2 hours'),
            'created_by' => User::factory()->admin(),
        ];
    }

    public function internal(): static
    {
        return $this->state(fn () => ['type' => EventType::Internal]);
    }

    public function external(): static
    {
        return $this->state(fn () => ['type' => EventType::External]);
    }
}
