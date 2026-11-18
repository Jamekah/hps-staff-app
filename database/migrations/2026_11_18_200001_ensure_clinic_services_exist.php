<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The six SM Clinic services, as a data migration.
 *
 * Laravel Cloud runs `migrate` on every deploy but never runs seeders, so
 * `ClinicServiceSeeder` had never run in production — on 18 Nov 2026 the
 * booking form's service dropdown was simply empty, and it was fixed by hand.
 * Putting the rows in a migration means a rebuild, a new environment or a
 * staging copy can never start without them.
 *
 * Deliberately insert-only, matched on `name`:
 *  - existing rows are never updated, so the durations Jason may tune in
 *    production survive untouched, and on production this is a no-op;
 *  - the query builder is used rather than the Eloquent model, so a later
 *    change to ClinicService cannot break a migration that has already run
 *    elsewhere.
 *
 * `ClinicServiceSeeder` stays as it is — tests and local setup still use it.
 */
return new class extends Migration
{
    /**
     * Same values as ClinicServiceSeeder: 30-minute default, only
     * Rehabilitation may recur, all active.
     */
    protected array $services = [
        ['Physiotherapy consultation', false],
        ['Rehabilitation', true],
        ['Sports massage', false],
        ['Sports strapping', false],
        ['Recovery', false],
        ['Screening and FMS', false],
    ];

    public function up(): void
    {
        $existing = DB::table('clinic_services')->pluck('name')->all();

        $missing = [];

        foreach ($this->services as [$name, $allowsRecurrence]) {
            if (in_array($name, $existing, true)) {
                continue;
            }

            $missing[] = [
                'name' => $name,
                'default_duration_minutes' => 30,
                'allows_recurrence' => $allowsRecurrence,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($missing !== []) {
            DB::table('clinic_services')->insert($missing);
        }
    }

    /**
     * Intentionally empty. Appointments reference these services, so removing
     * them on a rollback would break existing bookings — and the rows may well
     * have predated this migration anyway.
     */
    public function down(): void
    {
        //
    }
};
