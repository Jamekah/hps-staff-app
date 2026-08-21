<?php

namespace Tests\Feature\Clinic;

use App\Enums\AppointmentStatus;
use App\Models\ClinicAppointment;
use App\Models\Event;
use App\Models\GymSchedule;
use App\Models\User;
use App\Services\StaffAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The conflict engine. Every module that can occupy someone's day has to be
 * seen here, and the overlap test must be half-open so back-to-back bookings
 * remain legal.
 */
class StaffAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected StaffAvailability $availability;

    protected function setUp(): void
    {
        parent::setUp();

        $this->availability = app(StaffAvailability::class);
    }

    public function test_a_clinician_with_an_empty_schedule_is_free(): void
    {
        $clinician = User::factory()->clinician()->create();

        $this->assertTrue($this->availability->isFree(
            $clinician->id,
            today()->setTime(9, 0),
            today()->setTime(9, 30),
        ));
    }

    public function test_an_overlapping_event_blocks(): void
    {
        $clinician = User::factory()->clinician()->create();

        $event = Event::factory()->create([
            'starts_at' => today()->setTime(9, 0),
            'ends_at' => today()->setTime(11, 0),
        ]);
        $event->staff()->attach($clinician);

        $this->assertFalse($this->availability->isFree(
            $clinician->id,
            today()->setTime(10, 0),
            today()->setTime(10, 30),
        ));
    }

    public function test_an_event_the_clinician_is_not_assigned_to_does_not_block(): void
    {
        $clinician = User::factory()->clinician()->create();
        $someoneElse = User::factory()->create();

        $event = Event::factory()->create([
            'starts_at' => today()->setTime(9, 0),
            'ends_at' => today()->setTime(11, 0),
        ]);
        $event->staff()->attach($someoneElse);

        $this->assertTrue($this->availability->isFree(
            $clinician->id,
            today()->setTime(10, 0),
            today()->setTime(10, 30),
        ));
    }

    public function test_an_overlapping_gym_occurrence_blocks(): void
    {
        $clinician = User::factory()->clinician()->create();

        $session = GymSchedule::factory()->create([
            'start_date' => today(),
            'end_date' => today(),
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
        ]);
        $session->staff()->attach($clinician);

        $this->assertFalse($this->availability->isFree(
            $clinician->id,
            today()->setTime(10, 0),
            today()->setTime(10, 30),
        ));
    }

    public function test_a_weekly_gym_occurrence_blocks_on_its_weekday_only(): void
    {
        $clinician = User::factory()->clinician()->create();

        // A Monday-only series running for the next month.
        $monday = today()->next(\Carbon\Carbon::MONDAY);
        $tuesday = $monday->copy()->addDay();

        $session = GymSchedule::factory()
            ->weekly(today()->toDateString(), today()->addMonth()->toDateString(), [\Carbon\Carbon::MONDAY])
            ->create(['start_time' => '09:00:00', 'end_time' => '10:00:00']);
        $session->staff()->attach($clinician);

        $this->assertFalse(
            $this->availability->isFree($clinician->id, $monday->copy()->setTime(9, 30), $monday->copy()->setTime(10, 0)),
            'Expected the Monday occurrence to block.'
        );

        $this->assertTrue(
            $this->availability->isFree($clinician->id, $tuesday->copy()->setTime(9, 30), $tuesday->copy()->setTime(10, 0)),
            'Expected no clash on a weekday the series does not run.'
        );
    }

    public function test_an_overlapping_scheduled_clinic_appointment_blocks(): void
    {
        $clinician = User::factory()->clinician()->create();

        ClinicAppointment::factory()
            ->at(today()->setTime(9, 0), 60)
            ->create(['assigned_staff_id' => $clinician->id]);

        $this->assertFalse($this->availability->isFree(
            $clinician->id,
            today()->setTime(9, 30),
            today()->setTime(10, 0),
        ));
    }

    public function test_back_to_back_appointments_do_not_clash(): void
    {
        $clinician = User::factory()->clinician()->create();

        ClinicAppointment::factory()
            ->at(today()->setTime(9, 0), 30)
            ->create(['assigned_staff_id' => $clinician->id]);

        // 09:30 starts exactly when the previous ends — half-open, so free.
        $this->assertTrue($this->availability->isFree(
            $clinician->id,
            today()->setTime(9, 30),
            today()->setTime(10, 0),
        ));
    }

    #[DataProvider('nonBlockingStatuses')]
    public function test_non_scheduled_appointments_do_not_block(AppointmentStatus $status): void
    {
        $clinician = User::factory()->clinician()->create();

        ClinicAppointment::factory()
            ->at(today()->setTime(9, 0), 60)
            ->status($status)
            ->create(['assigned_staff_id' => $clinician->id]);

        $this->assertTrue(
            $this->availability->isFree($clinician->id, today()->setTime(9, 30), today()->setTime(10, 0)),
            "Expected a {$status->value} appointment not to block."
        );
    }

    public static function nonBlockingStatuses(): array
    {
        return [
            'cancelled' => [AppointmentStatus::Cancelled],
            'completed' => [AppointmentStatus::Completed],
            'no show' => [AppointmentStatus::NoShow],
        ];
    }

    public function test_ignoring_an_appointment_lets_an_edit_not_clash_with_itself(): void
    {
        $clinician = User::factory()->clinician()->create();

        $appointment = ClinicAppointment::factory()
            ->at(today()->setTime(9, 0), 60)
            ->create(['assigned_staff_id' => $clinician->id]);

        $this->assertFalse(
            $this->availability->isFree($clinician->id, today()->setTime(9, 0), today()->setTime(10, 0)),
            'Sanity: without the ignore it should clash.'
        );

        $this->assertTrue(
            $this->availability->isFree(
                $clinician->id,
                today()->setTime(9, 0),
                today()->setTime(10, 0),
                $appointment->id
            ),
            'Editing an appointment must not clash against itself.'
        );
    }

    public function test_conflicts_reports_what_is_in_the_way(): void
    {
        $clinician = User::factory()->clinician()->create();

        $event = Event::factory()->create([
            'name' => 'Sponsor Visit',
            'starts_at' => today()->setTime(9, 0),
            'ends_at' => today()->setTime(11, 0),
        ]);
        $event->staff()->attach($clinician);

        $conflicts = $this->availability->conflicts(
            $clinician->id,
            today()->setTime(10, 0),
            today()->setTime(10, 30),
        );

        $this->assertCount(1, $conflicts);
        $this->assertSame('event', $conflicts->first()['type']);
        $this->assertSame('Sponsor Visit', $conflicts->first()['label']);
    }

    public function test_free_map_reports_each_clinician(): void
    {
        $busy = User::factory()->clinician()->create();
        $free = User::factory()->clinician()->create();

        ClinicAppointment::factory()
            ->at(today()->setTime(9, 0), 60)
            ->create(['assigned_staff_id' => $busy->id]);

        $map = $this->availability->freeMap(
            [$busy->id, $free->id],
            today()->setTime(9, 30),
            today()->setTime(10, 0),
        );

        $this->assertFalse($map[$busy->id]);
        $this->assertTrue($map[$free->id]);
    }
}
