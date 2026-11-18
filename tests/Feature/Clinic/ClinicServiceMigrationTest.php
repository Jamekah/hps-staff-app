<?php

namespace Tests\Feature\Clinic;

use App\Models\ClinicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The data migration that guarantees the six clinic services exist.
 *
 * It has to be safe to run against a database that already has them — which
 * production does — so the interesting case is the second run, not the first.
 */
class ClinicServiceMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function migration(): object
    {
        return require database_path('migrations/2026_11_18_200001_ensure_clinic_services_exist.php');
    }

    protected function runMigration(): void
    {
        $this->migration()->up();
    }

    public function test_it_creates_the_six_services_on_an_empty_table(): void
    {
        DB::table('clinic_services')->delete();

        $this->runMigration();

        $this->assertSame(6, ClinicService::count());

        foreach ([
            'Physiotherapy consultation',
            'Rehabilitation',
            'Sports massage',
            'Sports strapping',
            'Recovery',
            'Screening and FMS',
        ] as $name) {
            $this->assertDatabaseHas('clinic_services', [
                'name' => $name,
                'default_duration_minutes' => 30,
                'is_active' => true,
            ]);
        }
    }

    public function test_only_rehabilitation_may_recur(): void
    {
        DB::table('clinic_services')->delete();

        $this->runMigration();

        $this->assertTrue(ClinicService::where('name', 'Rehabilitation')->value('allows_recurrence'));
        $this->assertSame(1, ClinicService::where('allows_recurrence', true)->count());
    }

    // ---------- the production case ----------

    public function test_running_it_again_creates_no_duplicates(): void
    {
        DB::table('clinic_services')->delete();

        $this->runMigration();
        $this->runMigration();
        $this->runMigration();

        $this->assertSame(6, ClinicService::count());
    }

    public function test_it_never_overwrites_an_existing_row(): void
    {
        DB::table('clinic_services')->delete();

        // Production may well have tuned durations or deactivated a service.
        ClinicService::create([
            'name' => 'Rehabilitation',
            'default_duration_minutes' => 45,
            'allows_recurrence' => true,
            'is_active' => false,
        ]);

        $this->runMigration();

        $rehab = ClinicService::where('name', 'Rehabilitation')->first();

        $this->assertSame(45, $rehab->default_duration_minutes, 'A tuned duration must survive.');
        $this->assertFalse($rehab->is_active, 'A deactivated service must stay deactivated.');
        $this->assertSame(6, ClinicService::count(), 'The other five are still added.');
    }

    public function test_it_is_a_no_op_when_every_service_is_present(): void
    {
        DB::table('clinic_services')->delete();
        $this->runMigration();

        $before = ClinicService::orderBy('name')->get()->map->only(['id', 'name', 'default_duration_minutes', 'allows_recurrence', 'is_active']);

        $this->runMigration();

        $after = ClinicService::orderBy('name')->get()->map->only(['id', 'name', 'default_duration_minutes', 'allows_recurrence', 'is_active']);

        $this->assertEquals($before->all(), $after->all());
    }

    public function test_services_exist_after_a_plain_migrate(): void
    {
        // RefreshDatabase has already migrated; the rows must simply be there,
        // with no seeder having run.
        $this->assertSame(6, ClinicService::count());
    }

    public function test_down_leaves_the_services_alone(): void
    {
        $this->migration()->down();

        $this->assertSame(6, ClinicService::count());
    }
}
