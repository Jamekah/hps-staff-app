<?php

namespace Database\Factories;

use App\Models\ClinicService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicService>
 */
class ClinicServiceFactory extends Factory
{
    protected $model = ClinicService::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Physiotherapy consultation',
                'Sports massage',
                'Sports strapping',
                'Recovery',
                'Screening and FMS',
            ]),
            'default_duration_minutes' => 30,
            'allows_recurrence' => false,
            'is_active' => true,
        ];
    }

    /** Rehabilitation is the only service that may recur. */
    public function recurring(): static
    {
        return $this->state(fn () => [
            'name' => 'Rehabilitation',
            'allows_recurrence' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
