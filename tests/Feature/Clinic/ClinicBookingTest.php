<?php

namespace Tests\Feature\Clinic;

use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentSeries;
use App\Models\ClinicClient;
use App\Models\ClinicService;
use App\Models\User;
use App\Notifications\ClinicAppointmentAssigned;
use App\Services\ClinicBooking;
use App\Services\ClinicClashException;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClinicBookingTest extends TestCase
{
    use RefreshDatabase;

    protected ClinicBooking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->booking = app(ClinicBooking::class);
        Notification::fake();
    }

    protected function attributes(User $clinician, User $booker, Carbon $start, int $minutes = 30): array
    {
        return [
            'clinic_client_id' => ClinicClient::factory()->create()->id,
            'clinic_service_id' => ClinicService::factory()->create()->id,
            'assigned_staff_id' => $clinician->id,
            'booked_by' => $booker->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes($minutes),
        ];
    }

    public function test_a_single_booking_is_created_and_the_clinician_is_notified(): void
    {
        $clinician = User::factory()->clinician()->create();
        $booker = User::factory()->admin()->create();

        $appointment = $this->booking->bookSingle(
            $this->attributes($clinician, $booker, today()->setTime(9, 0))
        );

        $this->assertDatabaseHas('clinic_appointments', [
            'id' => $appointment->id,
            'assigned_staff_id' => $clinician->id,
            'status' => 'scheduled',
            'has_clash' => false,
        ]);

        Notification::assertSentTo($clinician, ClinicAppointmentAssigned::class);
    }

    public function test_a_single_booking_is_hard_blocked_when_the_clinician_clashes(): void
    {
        $clinician = User::factory()->clinician()->create();
        $booker = User::factory()->admin()->create();

        ClinicAppointment::factory()
            ->at(today()->setTime(9, 0), 60)
            ->create(['assigned_staff_id' => $clinician->id]);

        $this->expectException(ClinicClashException::class);

        $this->booking->bookSingle(
            $this->attributes($clinician, $booker, today()->setTime(9, 30))
        );
    }

    public function test_a_blocked_booking_writes_nothing(): void
    {
        $clinician = User::factory()->clinician()->create();
        $booker = User::factory()->admin()->create();

        ClinicAppointment::factory()
            ->at(today()->setTime(9, 0), 60)
            ->create(['assigned_staff_id' => $clinician->id]);

        try {
            $this->booking->bookSingle(
                $this->attributes($clinician, $booker, today()->setTime(9, 30))
            );
        } catch (ClinicClashException) {
            // expected
        }

        $this->assertSame(1, ClinicAppointment::count());
    }

    public function test_a_recurring_series_materialises_one_row_per_occurrence(): void
    {
        $clinician = User::factory()->clinician()->create();
        $booker = User::factory()->admin()->create();

        // Two weeks of Mon/Wed/Fri = 6 occurrences.
        $start = today()->next(Carbon::MONDAY);

        $result = $this->booking->bookSeries([
            'clinic_client_id' => ClinicClient::factory()->create()->id,
            'clinic_service_id' => ClinicService::factory()->recurring()->create()->id,
            'assigned_staff_id' => $clinician->id,
            'booked_by' => $booker->id,
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'recurrence' => 'weekly',
            'days_of_week' => [Carbon::MONDAY, Carbon::WEDNESDAY, Carbon::FRIDAY],
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(13),
        ]);

        $this->assertCount(6, $result['series']->appointments);
        $this->assertEmpty($result['clashes']);
    }

    public function test_a_recurring_series_flags_clashing_occurrences_without_blocking(): void
    {
        $clinician = User::factory()->clinician()->create();
        $booker = User::factory()->admin()->create();

        $start = today()->next(Carbon::MONDAY);

        // Occupy the clinician on the very first occurrence only.
        ClinicAppointment::factory()
            ->at($start->copy()->setTime(9, 0), 60)
            ->create(['assigned_staff_id' => $clinician->id]);

        $result = $this->booking->bookSeries([
            'clinic_client_id' => ClinicClient::factory()->create()->id,
            'clinic_service_id' => ClinicService::factory()->recurring()->create()->id,
            'assigned_staff_id' => $clinician->id,
            'booked_by' => $booker->id,
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'recurrence' => 'weekly',
            'days_of_week' => [Carbon::MONDAY],
            'start_date' => $start,
            'end_date' => $start->copy()->addWeeks(3),
        ]);

        // Series still created — recurring bookings are never blocked.
        $this->assertCount(4, $result['series']->appointments);
        $this->assertCount(1, $result['clashes'], 'Exactly one occurrence should clash.');

        $flagged = ClinicAppointment::where('series_id', $result['series']->id)
            ->where('has_clash', true)
            ->get();

        $this->assertCount(1, $flagged);
        $this->assertTrue($flagged->first()->starts_at->isSameDay($start));
    }

    public function test_a_series_sends_one_summary_notice_not_one_per_occurrence(): void
    {
        $clinician = User::factory()->clinician()->create();
        $booker = User::factory()->admin()->create();

        $start = today()->next(Carbon::MONDAY);

        $this->booking->bookSeries([
            'clinic_client_id' => ClinicClient::factory()->create()->id,
            'clinic_service_id' => ClinicService::factory()->recurring()->create()->id,
            'assigned_staff_id' => $clinician->id,
            'booked_by' => $booker->id,
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'recurrence' => 'weekly',
            'days_of_week' => [Carbon::MONDAY],
            'start_date' => $start,
            'end_date' => $start->copy()->addWeeks(3),
        ]);

        Notification::assertSentToTimes($clinician, ClinicAppointmentAssigned::class, 1);
    }

    public function test_a_daily_series_covers_every_day_including_boundaries(): void
    {
        $clinician = User::factory()->clinician()->create();
        $booker = User::factory()->admin()->create();

        $start = today()->addDay();

        $result = $this->booking->bookSeries([
            'clinic_client_id' => ClinicClient::factory()->create()->id,
            'clinic_service_id' => ClinicService::factory()->recurring()->create()->id,
            'assigned_staff_id' => $clinician->id,
            'booked_by' => $booker->id,
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'recurrence' => 'daily',
            'days_of_week' => null,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(4),
        ]);

        // Inclusive of both boundary dates.
        $this->assertCount(5, $result['series']->appointments);
    }

    public function test_preview_reports_clashes_without_writing_anything(): void
    {
        $clinician = User::factory()->clinician()->create();
        $booker = User::factory()->admin()->create();

        $start = today()->next(Carbon::MONDAY);

        ClinicAppointment::factory()
            ->at($start->copy()->setTime(9, 0), 60)
            ->create(['assigned_staff_id' => $clinician->id]);

        $draft = new ClinicAppointmentSeries([
            'clinic_client_id' => ClinicClient::factory()->create()->id,
            'clinic_service_id' => ClinicService::factory()->recurring()->create()->id,
            'assigned_staff_id' => $clinician->id,
            'booked_by' => $booker->id,
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'recurrence' => 'weekly',
            'days_of_week' => [Carbon::MONDAY],
            'start_date' => $start,
            'end_date' => $start->copy()->addWeeks(3),
        ]);

        $clashes = $this->booking->previewSeriesClashes($draft);

        $this->assertCount(1, $clashes);
        $this->assertSame(1, ClinicAppointment::count(), 'Preview must not persist anything.');
        $this->assertSame(0, ClinicAppointmentSeries::count());
    }

    public function test_clinician_availability_marks_who_is_free(): void
    {
        $busy = User::factory()->clinician()->create(['name' => 'Busy']);
        $free = User::factory()->clinician()->create(['name' => 'Free']);
        User::factory()->create(['name' => 'Not a clinician']);

        ClinicAppointment::factory()
            ->at(today()->setTime(9, 0), 60)
            ->create(['assigned_staff_id' => $busy->id]);

        $availability = $this->booking->clinicianAvailability(
            today()->setTime(9, 30),
            today()->setTime(10, 0)
        );

        $this->assertCount(2, $availability, 'Only clinicians are offered.');
        $this->assertFalse($availability[$busy->id]['free']);
        $this->assertTrue($availability[$free->id]['free']);
    }

    public function test_inactive_clinicians_are_not_offered(): void
    {
        User::factory()->clinician()->inactive()->create();

        $availability = $this->booking->clinicianAvailability(
            today()->setTime(9, 0),
            today()->setTime(9, 30)
        );

        $this->assertEmpty($availability);
    }

    public function test_end_time_derives_from_the_service_default_duration(): void
    {
        $service = ClinicService::factory()->create(['default_duration_minutes' => 45]);

        $end = $this->booking->defaultEnd($service, today()->setTime(9, 0));

        $this->assertSame('09:45', $end->format('H:i'));
    }

    public function test_a_per_booking_duration_override_is_honoured(): void
    {
        $clinician = User::factory()->clinician()->create();
        $booker = User::factory()->admin()->create();
        $service = ClinicService::factory()->create(['default_duration_minutes' => 30]);

        // Booker overrides 30 minutes with 90.
        $appointment = $this->booking->bookSingle([
            ...$this->attributes($clinician, $booker, today()->setTime(9, 0)),
            'clinic_service_id' => $service->id,
            'ends_at' => today()->setTime(10, 30),
        ]);

        $this->assertEqualsWithDelta(90, $appointment->starts_at->diffInMinutes($appointment->ends_at), 0.01);
        $this->assertSame('10:30', $appointment->ends_at->format('H:i'));
    }
}
