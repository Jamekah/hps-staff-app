<?php

namespace Tests\Feature\Availability;

use App\Enums\LeaveType;
use App\Livewire\Calendar\EventsCalendar;
use App\Livewire\Clinic\ClinicSchedule;
use App\Livewire\Gym\GymSchedulePage;
use App\Models\ClinicAppointment;
use App\Models\ClinicClient;
use App\Models\ClinicService;
use App\Models\Event;
use App\Models\StaffLeave;
use App\Models\User;
use App\Services\ClinicBooking;
use App\Services\ClinicClashException;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * How leave behaves in each module.
 *
 * The policy differs on purpose: events and one-off gym sessions hard-block a
 * new assignment, recurring gym series and recurring rehab flag instead, and
 * single clinic bookings block through the conflict engine.
 */
class LeaveEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    protected function away(User $user, string $first = '2026-12-07', string $return = '2026-12-14'): StaffLeave
    {
        return StaffLeave::factory()->create([
            'user_id' => $user->id,
            'type' => LeaveType::Annual,
            'first_day' => $first,
            'return_date' => $return,
        ]);
    }

    // ================= Events =================

    public function test_a_newly_assigned_staff_member_on_leave_is_rejected(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi']);
        $this->away($staff);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EventsCalendar::class)
            ->call('openCreate')
            ->set('name', 'Squad Review')
            ->set('starts_at', '2026-12-08T09:00')
            ->set('ends_at', '2026-12-08T11:00')
            ->set('staffIds', [(string) $staff->id])
            ->call('save')
            ->assertHasErrors('staffIds');

        $this->assertDatabaseMissing('events', ['name' => 'Squad Review']);
    }

    public function test_the_rejection_names_the_person(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi']);
        $this->away($staff);

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(EventsCalendar::class)
            ->call('openCreate')
            ->set('name', 'Squad Review')
            ->set('starts_at', '2026-12-08T09:00')
            ->set('ends_at', '2026-12-08T11:00')
            ->set('staffIds', [(string) $staff->id])
            ->call('save');

        $this->assertStringContainsString('Kila Vagi', $component->errors()->first('staffIds'));
    }

    public function test_staff_who_are_free_can_still_be_assigned(): void
    {
        $staff = User::factory()->create();
        $this->away($staff, '2026-12-20', '2026-12-24');

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EventsCalendar::class)
            ->call('openCreate')
            ->set('name', 'Squad Review')
            ->set('starts_at', '2026-12-08T09:00')
            ->set('ends_at', '2026-12-08T11:00')
            ->set('staffIds', [(string) $staff->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('events', ['name' => 'Squad Review']);
    }

    public function test_an_already_assigned_person_on_leave_does_not_block_other_edits(): void
    {
        $staff = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $event = Event::factory()->create([
            'name' => 'Squad Review',
            'starts_at' => '2026-12-08 09:00',
            'ends_at' => '2026-12-08 11:00',
        ]);
        $event->staff()->attach($staff);

        // Leave recorded after the assignment.
        $this->away($staff);

        Livewire::actingAs($admin)
            ->test(EventsCalendar::class)
            ->call('openEdit', $event->id)
            ->set('name', 'Squad Review (moved room)')
            ->call('save')
            ->assertHasNoErrors();

        $event->refresh();

        $this->assertSame('Squad Review (moved room)', $event->name);
        $this->assertTrue($event->staff->contains('id', $staff->id), 'They stay assigned until an admin says otherwise.');
    }

    public function test_the_picker_marks_people_on_leave(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi']);
        $this->away($staff);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EventsCalendar::class)
            ->call('openCreate')
            ->set('starts_at', '2026-12-08T09:00')
            ->set('ends_at', '2026-12-08T11:00')
            ->assertSee('On leave')
            ->assertSee('back 14 Dec');
    }

    public function test_the_event_detail_sheet_badges_staff_who_are_away(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi']);

        $event = Event::factory()->create([
            'starts_at' => '2026-12-08 09:00',
            'ends_at' => '2026-12-08 11:00',
        ]);
        $event->staff()->attach($staff);
        $this->away($staff);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EventsCalendar::class)
            ->call('selectEvent', $event->id)
            ->assertSee('Away');
    }

    // ================= Gym — one-off =================

    public function test_a_one_off_gym_session_rejects_someone_on_leave(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi']);
        $this->away($staff);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(GymSchedulePage::class)
            ->call('openCreate')
            ->set('name', 'Strength')
            ->set('client_name', 'Rugby 7s')
            ->set('recurrence', 'none')
            ->set('start_date', '2026-12-08')
            ->set('end_date', '2026-12-08')
            ->set('start_time', '09:00')
            ->set('end_time', '10:00')
            ->set('staffIds', [(string) $staff->id])
            ->call('save')
            ->assertHasErrors('staffIds');

        $this->assertDatabaseMissing('gym_schedules', ['name' => 'Strength']);
    }

    public function test_a_one_off_gym_session_on_the_return_date_is_allowed(): void
    {
        $staff = User::factory()->create();
        $this->away($staff, '2026-12-07', '2026-12-08');

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(GymSchedulePage::class)
            ->call('openCreate')
            ->set('name', 'Strength')
            ->set('client_name', 'Rugby 7s')
            ->set('recurrence', 'none')
            ->set('start_date', '2026-12-08')
            ->set('end_date', '2026-12-08')
            ->set('start_time', '09:00')
            ->set('end_time', '10:00')
            ->set('staffIds', [(string) $staff->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('gym_schedules', ['name' => 'Strength']);
    }

    // ================= Gym — recurring series =================

    public function test_a_recurring_series_saves_and_reports_the_flagged_dates(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi']);

        // Away Wed 9 Dec and Thu 10 Dec 2026, back Friday 11th.
        $this->away($staff, '2026-12-09', '2026-12-11');

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(GymSchedulePage::class)
            ->call('openCreate')
            ->set('name', 'Daily Conditioning')
            ->set('client_name', 'Rugby 7s')
            ->set('recurrence', 'daily')
            ->set('start_date', '2026-12-07')
            ->set('end_date', '2026-12-13')
            ->set('start_time', '09:00')
            ->set('end_time', '10:00')
            ->set('staffIds', [(string) $staff->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('gym_schedules', ['name' => 'Daily Conditioning']);

        $summary = $component->get('seriesLeaveSummary');

        $this->assertCount(1, $summary);
        $this->assertSame('Kila Vagi', $summary[0]['name']);
        $this->assertSame(2, $summary[0]['count']);
        $this->assertSame(7, $summary[0]['total']);
        $this->assertSame(['9 Dec', '10 Dec'], $summary[0]['dates']);
    }

    public function test_a_weekly_series_only_flags_the_weekdays_it_actually_runs(): void
    {
        $staff = User::factory()->create();

        // Away for the whole of the week beginning Monday 7 Dec 2026.
        $this->away($staff, '2026-12-07', '2026-12-14');

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(GymSchedulePage::class)
            ->call('openCreate')
            ->set('name', 'Mon/Wed Strength')
            ->set('client_name', 'Rugby 7s')
            ->set('recurrence', 'weekly')
            ->set('days_of_week', ['1', '3'])
            ->set('start_date', '2026-12-07')
            ->set('end_date', '2026-12-20')
            ->set('start_time', '09:00')
            ->set('end_time', '10:00')
            ->set('staffIds', [(string) $staff->id])
            ->call('save')
            ->assertHasNoErrors();

        $summary = $component->get('seriesLeaveSummary');

        $this->assertSame(2, $summary[0]['count'], 'Only the Mon and Wed inside the leave.');
        $this->assertSame(4, $summary[0]['total']);
        $this->assertSame(['7 Dec', '9 Dec'], $summary[0]['dates']);
    }

    public function test_a_series_with_nobody_on_leave_reports_nothing(): void
    {
        $staff = User::factory()->create();

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(GymSchedulePage::class)
            ->call('openCreate')
            ->set('name', 'Daily Conditioning')
            ->set('client_name', 'Rugby 7s')
            ->set('recurrence', 'daily')
            ->set('start_date', '2026-12-07')
            ->set('end_date', '2026-12-13')
            ->set('start_time', '09:00')
            ->set('end_time', '10:00')
            ->set('staffIds', [(string) $staff->id])
            ->call('save');

        $this->assertSame([], $component->get('seriesLeaveSummary'));
    }

    public function test_the_gym_detail_sheet_badges_staff_away_that_day(): void
    {
        $staff = User::factory()->create();

        $session = \App\Models\GymSchedule::factory()->create([
            'start_date' => '2026-12-07',
            'end_date' => '2026-12-13',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'recurrence' => 'daily',
        ]);
        $session->staff()->attach($staff);
        $this->away($staff, '2026-12-08', '2026-12-10');

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(GymSchedulePage::class)
            ->set('date', '2026-12-08')
            ->call('selectSession', $session->id)
            ->assertSee('Away');
    }

    // ================= Clinic =================

    protected function clinicAttributes(User $clinician, User $booker, Carbon $start): array
    {
        return [
            'clinic_client_id' => ClinicClient::factory()->create()->id,
            'clinic_service_id' => ClinicService::factory()->create()->id,
            'assigned_staff_id' => $clinician->id,
            'booked_by' => $booker->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(30),
        ];
    }

    public function test_a_single_clinic_booking_is_blocked_by_leave(): void
    {
        $clinician = User::factory()->clinician()->create();
        $this->away($clinician);

        $this->expectException(ClinicClashException::class);

        app(ClinicBooking::class)->bookSingle(
            $this->clinicAttributes($clinician, User::factory()->admin()->create(), Carbon::parse('2026-12-08 09:00'))
        );
    }

    public function test_recurring_rehab_flags_the_occurrences_inside_leave(): void
    {
        $clinician = User::factory()->clinician()->create();
        $booker = User::factory()->admin()->create();

        $this->away($clinician, '2026-12-09', '2026-12-11');

        $result = app(ClinicBooking::class)->bookSeries([
            'clinic_client_id' => ClinicClient::factory()->create()->id,
            'clinic_service_id' => ClinicService::factory()->create(['allows_recurrence' => true])->id,
            'assigned_staff_id' => $clinician->id,
            'booked_by' => $booker->id,
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'recurrence' => 'daily',
            'days_of_week' => null,
            'start_date' => '2026-12-07',
            'end_date' => '2026-12-13',
        ]);

        $this->assertCount(2, $result['clashes'], 'The two days inside the leave.');
        $this->assertSame(7, $result['series']->appointments()->count());
        $this->assertSame(2, ClinicAppointment::where('has_clash', true)->count());
    }

    public function test_the_clinician_picker_greys_out_someone_on_leave_without_naming_the_type(): void
    {
        $clinician = User::factory()->clinician()->create(['name' => 'Kila Vagi']);

        StaffLeave::factory()->create([
            'user_id' => $clinician->id,
            'type' => LeaveType::Sick,
            'first_day' => '2026-12-07',
            'return_date' => '2026-12-14',
        ]);

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(ClinicSchedule::class)
            ->call('openCreate')
            ->set('appointment_date', '2026-12-08')
            ->set('start_time', '09:00')
            ->set('end_time', '09:30');

        $component->assertSee('On leave');

        // The reason must never leak the type, here of all places.
        foreach ([LeaveType::Sick->label(), 'Sick', 'sick'] as $forbidden) {
            $component->assertDontSee($forbidden);
        }
    }

    public function test_a_free_clinician_is_not_marked_on_leave(): void
    {
        User::factory()->clinician()->create(['name' => 'Free Clinician']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(ClinicSchedule::class)
            ->call('openCreate')
            ->set('appointment_date', '2026-12-08')
            ->set('start_time', '09:00')
            ->set('end_time', '09:30')
            ->assertDontSee('On leave');
    }

    // ================= has_clash recompute =================

    public function test_recording_leave_flags_existing_clinic_appointments(): void
    {
        $clinician = User::factory()->clinician()->create();

        $appointment = ClinicAppointment::factory()->create([
            'assigned_staff_id' => $clinician->id,
            'starts_at' => '2026-12-08 09:00',
            'ends_at' => '2026-12-08 09:30',
            'has_clash' => false,
        ]);

        $this->away($clinician);

        $this->assertTrue($appointment->fresh()->has_clash);
    }

    public function test_cancelling_leave_clears_the_flag_again(): void
    {
        $clinician = User::factory()->clinician()->create();

        $appointment = ClinicAppointment::factory()->create([
            'assigned_staff_id' => $clinician->id,
            'starts_at' => '2026-12-08 09:00',
            'ends_at' => '2026-12-08 09:30',
            'has_clash' => false,
        ]);

        $leave = $this->away($clinician);
        $this->assertTrue($appointment->fresh()->has_clash);

        $leave->update(['cancelled_at' => now(), 'cancelled_by' => $clinician->id]);

        $this->assertFalse($appointment->fresh()->has_clash);
    }

    public function test_editing_leave_recomputes_both_the_old_and_the_new_range(): void
    {
        $clinician = User::factory()->clinician()->create();

        $inOldRange = ClinicAppointment::factory()->create([
            'assigned_staff_id' => $clinician->id,
            'starts_at' => '2026-12-08 09:00',
            'ends_at' => '2026-12-08 09:30',
        ]);

        $inNewRange = ClinicAppointment::factory()->create([
            'assigned_staff_id' => $clinician->id,
            'starts_at' => '2026-12-22 09:00',
            'ends_at' => '2026-12-22 09:30',
        ]);

        $leave = $this->away($clinician, '2026-12-07', '2026-12-14');

        $this->assertTrue($inOldRange->fresh()->has_clash);
        $this->assertFalse($inNewRange->fresh()->has_clash);

        // Move the leave a fortnight later.
        $leave->update(['first_day' => '2026-12-21', 'return_date' => '2026-12-28']);

        $this->assertFalse($inOldRange->fresh()->has_clash, 'The old range must be freed.');
        $this->assertTrue($inNewRange->fresh()->has_clash, 'The new range must be flagged.');
    }

    public function test_a_cancelled_appointment_is_never_flagged(): void
    {
        $clinician = User::factory()->clinician()->create();

        $appointment = ClinicAppointment::factory()->create([
            'assigned_staff_id' => $clinician->id,
            'starts_at' => '2026-12-08 09:00',
            'ends_at' => '2026-12-08 09:30',
            'status' => \App\Enums\AppointmentStatus::Cancelled,
            'has_clash' => false,
        ]);

        $this->away($clinician);

        $this->assertFalse($appointment->fresh()->has_clash);
    }
}
