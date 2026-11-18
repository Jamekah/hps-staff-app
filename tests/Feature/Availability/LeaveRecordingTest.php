<?php

namespace Tests\Feature\Availability;

use App\Enums\LeaveType;
use App\Livewire\Availability\StaffAvailabilityPage;
use App\Models\Event;
use App\Models\StaffLeave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Recording, editing and cancelling leave.
 *
 * The form is deliberately small: staff, type, two dates and an optional note.
 * Everything else it shows — working days, the back date, the conflict report
 * — is derived.
 */
class LeaveRecordingTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_an_admin_can_record_leave(): void
    {
        $staff = User::factory()->create();
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm')
            ->set('leaveUserId', $staff->id)
            ->set('leaveType', LeaveType::Annual->value)
            ->set('firstDay', '2026-12-07')
            ->set('returnDate', '2026-12-14')
            ->set('leaveNote', 'Family trip.')
            ->call('saveLeave')
            ->assertHasNoErrors()
            ->assertSet('showLeaveForm', false);

        $this->assertDatabaseHas('staff_leaves', [
            'user_id' => $staff->id,
            'type' => LeaveType::Annual->value,
            'note' => 'Family trip.',
            'recorded_by' => $admin->id,
            'cancelled_at' => null,
        ]);
    }

    // ---------- Validation ----------

    public function test_the_return_date_must_be_after_the_first_day(): void
    {
        $staff = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm')
            ->set('leaveUserId', $staff->id)
            ->set('firstDay', '2026-12-07')
            ->set('returnDate', '2026-12-07')
            ->call('saveLeave')
            ->assertHasErrors('returnDate');

        $this->assertSame(0, StaffLeave::count());
    }

    public function test_one_day_is_the_shortest_leave(): void
    {
        $staff = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm')
            ->set('leaveUserId', $staff->id)
            ->set('firstDay', '2026-12-07')
            ->set('returnDate', '2026-12-08')
            ->call('saveLeave')
            ->assertHasNoErrors();

        $this->assertSame(1, StaffLeave::first()->calendarDays());
    }

    public function test_an_inactive_staff_member_is_rejected(): void
    {
        $staff = User::factory()->inactive()->create();

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm')
            ->set('leaveUserId', $staff->id)
            ->set('firstDay', '2026-12-07')
            ->set('returnDate', '2026-12-09')
            ->call('saveLeave')
            ->assertHasErrors('leaveUserId');

        $this->assertSame(0, StaffLeave::count());
    }

    public function test_overlapping_leave_for_the_same_person_is_rejected_and_named(): void
    {
        $staff = User::factory()->create();

        StaffLeave::factory()->create([
            'user_id' => $staff->id,
            'type' => LeaveType::Annual,
            'first_day' => '2026-12-07',
            'return_date' => '2026-12-14',
        ]);

        $component = Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm')
            ->set('leaveUserId', $staff->id)
            ->set('leaveType', LeaveType::Sick->value)
            ->set('firstDay', '2026-12-10')
            ->set('returnDate', '2026-12-18')
            ->call('saveLeave')
            ->assertHasErrors('firstDay');

        $this->assertStringContainsString('Annual leave', $component->errors()->first('firstDay'));
        $this->assertSame(1, StaffLeave::count());
    }

    public function test_leave_that_starts_on_the_return_date_of_another_is_allowed(): void
    {
        $staff = User::factory()->create();

        StaffLeave::factory()->create([
            'user_id' => $staff->id,
            'first_day' => '2026-12-07',
            'return_date' => '2026-12-14',
        ]);

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm')
            ->set('leaveUserId', $staff->id)
            ->set('firstDay', '2026-12-14')
            ->set('returnDate', '2026-12-18')
            ->call('saveLeave')
            ->assertHasNoErrors();

        $this->assertSame(2, StaffLeave::count());
    }

    public function test_cancelled_leave_does_not_block_a_new_record(): void
    {
        $staff = User::factory()->create();

        StaffLeave::factory()->cancelled()->create([
            'user_id' => $staff->id,
            'first_day' => '2026-12-07',
            'return_date' => '2026-12-14',
        ]);

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm')
            ->set('leaveUserId', $staff->id)
            ->set('firstDay', '2026-12-08')
            ->set('returnDate', '2026-12-12')
            ->call('saveLeave')
            ->assertHasNoErrors();

        $this->assertSame(1, StaffLeave::active()->count());
    }

    public function test_past_leave_can_be_recorded(): void
    {
        $staff = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm')
            ->set('leaveUserId', $staff->id)
            ->set('firstDay', now()->subMonth()->toDateString())
            ->set('returnDate', now()->subMonth()->addDays(5)->toDateString())
            ->call('saveLeave')
            ->assertHasNoErrors();

        $this->assertSame(1, StaffLeave::count());
    }

    // ---------- Editing ----------

    public function test_editing_re_runs_the_overlap_check(): void
    {
        $staff = User::factory()->create();

        $first = StaffLeave::factory()->create([
            'user_id' => $staff->id,
            'first_day' => '2026-12-07',
            'return_date' => '2026-12-10',
        ]);

        $second = StaffLeave::factory()->create([
            'user_id' => $staff->id,
            'first_day' => '2026-12-20',
            'return_date' => '2026-12-24',
        ]);

        // Dragging the second back over the first must be refused…
        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openEditLeave', $second->id)
            ->set('firstDay', '2026-12-08')
            ->set('returnDate', '2026-12-24')
            ->call('saveLeave')
            ->assertHasErrors('firstDay');

        $this->assertSame('2026-12-20', $second->fresh()->first_day->toDateString());

        // …while an edit that clears it is fine, and never clashes with itself.
        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openEditLeave', $second->id)
            ->set('firstDay', '2026-12-21')
            ->call('saveLeave')
            ->assertHasNoErrors();

        $this->assertSame('2026-12-21', $second->fresh()->first_day->toDateString());
        $this->assertSame('2026-12-07', $first->fresh()->first_day->toDateString());
    }

    public function test_an_edit_can_change_the_type_and_note(): void
    {
        $leave = StaffLeave::factory()->create([
            'user_id' => User::factory()->create()->id,
            'type' => LeaveType::Annual,
            'first_day' => '2026-12-07',
            'return_date' => '2026-12-10',
        ]);

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openEditLeave', $leave->id)
            ->set('leaveType', LeaveType::Sick->value)
            ->set('leaveNote', 'Flu.')
            ->call('saveLeave')
            ->assertHasNoErrors();

        $leave->refresh();

        $this->assertSame(LeaveType::Sick, $leave->type);
        $this->assertSame('Flu.', $leave->note);
    }

    // ---------- Cancelling ----------

    public function test_cancelling_keeps_the_row_as_history(): void
    {
        $admin = $this->admin();

        $leave = StaffLeave::factory()->create([
            'user_id' => User::factory()->create()->id,
            'first_day' => '2026-12-07',
            'return_date' => '2026-12-10',
        ]);

        Livewire::actingAs($admin)
            ->test(StaffAvailabilityPage::class)
            ->call('cancelLeave', $leave->id);

        $leave->refresh();

        $this->assertNotNull($leave->cancelled_at);
        $this->assertSame($admin->id, $leave->cancelled_by);
        $this->assertDatabaseHas('staff_leaves', ['id' => $leave->id]);
    }

    public function test_cancelling_twice_is_refused(): void
    {
        $leave = StaffLeave::factory()->cancelled()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('cancelLeave', $leave->id)
            ->assertForbidden();
    }

    // ---------- Derived figures and the conflict report ----------

    public function test_the_live_summary_counts_working_and_calendar_days(): void
    {
        $staff = User::factory()->create();

        $component = Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm', $staff->id)
            ->set('firstDay', '2026-12-07')   // Monday
            ->set('returnDate', '2026-12-21'); // Monday, two weeks later

        $component->assertSee('10 working days')
            ->assertSee('(14 calendar)');
    }

    public function test_the_conflict_report_lists_existing_assignments(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi']);

        $event = Event::factory()->create([
            'name' => 'Squad Review',
            'starts_at' => '2026-12-08 09:00',
            'ends_at' => '2026-12-08 11:00',
        ]);
        $event->staff()->attach($staff);

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm', $staff->id)
            ->set('firstDay', '2026-12-07')
            ->set('returnDate', '2026-12-14')
            ->assertSee('Kila Vagi is assigned to 1 item during this leave')
            ->assertSee('Squad Review');
    }

    public function test_saving_never_removes_an_assignment(): void
    {
        $staff = User::factory()->create();

        $event = Event::factory()->create([
            'starts_at' => '2026-12-08 09:00',
            'ends_at' => '2026-12-08 11:00',
        ]);
        $event->staff()->attach($staff);

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm', $staff->id)
            ->set('firstDay', '2026-12-07')
            ->set('returnDate', '2026-12-14')
            ->call('saveLeave')
            ->assertHasNoErrors();

        $this->assertTrue($event->fresh()->staff->contains('id', $staff->id));
    }

    // ---------- Access ----------

    public function test_plain_staff_cannot_record_leave(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(StaffAvailabilityPage::class)
            ->assertForbidden();

        $this->assertSame(0, StaffLeave::count());
    }
}
