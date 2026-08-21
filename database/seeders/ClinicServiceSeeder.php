<?php

namespace Database\Seeders;

use App\Models\ClinicService;
use Illuminate\Database\Seeder;

/**
 * The six SM Clinic services. Durations are a placeholder 30 minutes until
 * real per-service values are supplied; duration is editable per booking
 * regardless. Rehabilitation is the only service that may recur.
 */
class ClinicServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['Physiotherapy consultation', false],
            ['Rehabilitation', true],
            ['Sports massage', false],
            ['Sports strapping', false],
            ['Recovery', false],
            ['Screening and FMS', false],
        ];

        foreach ($services as [$name, $allowsRecurrence]) {
            ClinicService::updateOrCreate(
                ['name' => $name],
                [
                    'default_duration_minutes' => 30,
                    'allows_recurrence' => $allowsRecurrence,
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('Seeded '.count($services).' clinic services.');
    }
}
