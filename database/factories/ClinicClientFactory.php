<?php

namespace Database\Factories;

use App\Models\ClinicClient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicClient>
 */
class ClinicClientFactory extends Factory
{
    protected $model = ClinicClient::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'organization' => fake()->randomElement([
                'PNG Rugby Union', 'PNG Athletics', 'PNG Netball', 'PNG Boxing', null,
            ]),
            'phone' => fake()->optional()->numerify('7### ####'),
            'email' => fake()->optional()->safeEmail(),
            'notes' => null,
        ];
    }
}
