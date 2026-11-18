<?php

namespace Tests\Feature\Availability;

use App\Enums\LeaveType;
use App\Livewire\Availability\StaffAvailabilityPage;
use App\Models\StaffLeave;
use App\Models\User;
use App\Services\LeaveAllowance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Leave stats panel, the over-limit flow, and the rule that every view
 * shows the same numbers.
 */
class LeaveStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create(['name' => 'The Admin']);
    }

    protected function leave(User $user, string $first, string $return, LeaveType $type = LeaveType::Annual): StaffLeave
    {
        return StaffLeave::factory()->create([
            'user_id' => $user->id,
            'type' => $type,
            'first_day' => $first,
            'return_date' => $return,
        ]);
    }

    // ================= The panel =================

    public function test_the_overview_shows_the_stats_panel(): void
    {
        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->assertSee('Leave stats')
            ->assertSee('Annual 15')
            ->assertSee('Sick 10')
            ->assertSee('Includes booked leave');
    }

    public function test_it_defaults_to_the_current_year(): void
    {
        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->assertSet('statsYear', 2026);
    }

    public function test_the_year_selector_changes_the_figures(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi']);

        $this->leave($staff, '2026-03-02', '2026-03-09');  // 5 days in 2026
        $this->leave($staff, '2025-03-03', '2025-03-24');  // 15 days in 2025

        $component = Livewire::actingAs($this->admin())->test(StaffAvailabilityPage::class);

        $this->assertSame(5, $component->viewData('stats')['teamByType']['annual']);

        $component->call('previousStatsYear')->assertSet('statsYear', 2025);
        $this->assertSame(15, $component->viewData('stats')['teamByType']['annual']);

        $component->call('goToThisYear')->assertSet('statsYear', 2026);
        $this->assertSame(5, $component->viewData('stats')['teamByType']['annual']);
    }

    public function test_the_year_selector_stops_at_the_earliest_year_with_leave(): void
    {
        $staff = User::factory()->create();
        $this->leave($staff, '2025-03-03', '2025-03-10');

        $component = Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('previousStatsYear')
            ->assertSet('statsYear', 2025);

        // Nothing before 2025, so it holds there.
        $component->call('previousStatsYear')->assertSet('statsYear', 2025);
    }

    public function test_it_includes_staff_with_no_leave_at_all(): void
    {
        User::factory()->create(['name' => 'Never Away']);

        $component = Livewire::actingAs($this->admin())->test(StaffAvailabilityPage::class);

        $component->assertSee('Never Away');

        $row = collect($component->viewData('stats')['rows'])
            ->firstWhere(fn (array $row) => $row['user']->name === 'Never Away');

        $this->assertSame(0, $row['stats']['total']);
        $this->assertSame(15, $row['stats']['byType']['annual']['remaining']);
    }

    public function test_deactivated_staff_are_hidden_until_asked_for(): void
    {
        User::factory()->inactive()->create(['name' => 'Left The Org']);

        $component = Livewire::actingAs($this->admin())->test(StaffAvailabilityPage::class);

        $component->assertDontSee('Left The Org');

        $component->set('statsIncludeDeactivated', true)->assertSee('Left The Org');
    }

    public function test_the_team_summary_counts_staff_over_a_limit(): void
    {
        $over = User::factory()->create(['name' => 'Over Person']);
        $under = User::factory()->create(['name' => 'Under Person']);

        $this->leave($over, '2026-02-02', '2026-03-02');   // 20 of 15 annual
        $this->leave($under, '2026-03-02', '2026-03-09');  // 5 of 15

        $component = Livewire::actingAs($this->admin())->test(StaffAvailabilityPage::class);

        $this->assertSame(1, $component->viewData('stats')['staffOverLimit']);
        $component->assertSee('1 staff over a limit')->assertSee('+5 over');
    }

    public function test_the_panel_can_be_sorted(): void
    {
        $small = User::factory()->create(['name' => 'Aaa Small']);
        $big = User::factory()->create(['name' => 'Zzz Big']);

        $this->leave($small, '2026-03-02', '2026-03-05');  // 3
        $this->leave($big, '2026-03-02', '2026-03-16');    // 10

        $component = Livewire::actingAs($this->admin())->test(StaffAvailabilityPage::class);

        $byName = collect($component->viewData('stats')['rows'])->pluck('user.name')->all();
        $this->assertSame(['Aaa Small', 'The Admin', 'Zzz Big'], $byName);

        $component->call('setStatsSort', 'annual');
        $byAnnual = collect($component->viewData('stats')['rows'])->pluck('user.name')->first();
        $this->assertSame('Zzz Big', $byAnnual);

        $component->call('setStatsSort', 'total');
        $this->assertSame('Zzz Big', collect($component->viewData('stats')['rows'])->pluck('user.name')->first());
    }

    public function test_an_unknown_sort_falls_back_to_name(): void
    {
        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('setStatsSort', 'nonsense')
            ->assertSet('statsSort', 'name');
    }

    public function test_the_panel_can_be_filtered(): void
    {
        User::factory()->create(['name' => 'Kila Vagi', 'position' => 'Physiotherapist']);
        User::factory()->create(['name' => 'Tom Bani', 'position' => 'S&C Coach']);

        $component = Livewire::actingAs($this->admin())->test(StaffAvailabilityPage::class);

        $component->set('statsSearch', 'Kila')->assertSee('Kila Vagi')->assertDontSee('Tom Bani');

        $component->set('statsSearch', '')->set('statsPositionFilter', 'S&C Coach')
            ->assertSee('Tom Bani')->assertDontSee('Kila Vagi');
    }

    // ================= Over-limit flow =================

    public function test_recording_within_the_limit_needs_no_confirmation(): void
    {
        $staff = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm', $staff->id)
            ->set('leaveType', LeaveType::Annual->value)
            ->set('firstDay', '2026-08-03')
            ->set('returnDate', '2026-08-10')
            ->call('saveLeave')
            ->assertHasNoErrors();

        $this->assertSame(1, StaffLeave::count());
    }

    public function test_going_over_the_limit_is_refused_without_confirmation(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi']);
        $this->leave($staff, '2026-02-02', '2026-02-19'); // 13 of 15

        $component = Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm', $staff->id)
            ->set('leaveType', LeaveType::Annual->value)
            ->set('firstDay', '2026-08-03')
            ->set('returnDate', '2026-08-10')  // a further 5 → 18
            ->call('saveLeave')
            ->assertHasErrors('leaveOverageAck');

        $this->assertStringContainsString('18 of 15', $component->errors()->first('leaveOverageAck'));
        $this->assertStringContainsString('3 over', $component->errors()->first('leaveOverageAck'));
        $this->assertSame(1, StaffLeave::count(), 'Nothing was saved.');
    }

    public function test_going_over_the_limit_saves_once_confirmed(): void
    {
        $staff = User::factory()->create();
        $this->leave($staff, '2026-02-02', '2026-02-19');

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm', $staff->id)
            ->set('leaveType', LeaveType::Annual->value)
            ->set('firstDay', '2026-08-03')
            ->set('returnDate', '2026-08-10')
            ->set('leaveOverageAck', true)
            ->call('saveLeave')
            ->assertHasNoErrors();

        $this->assertSame(2, StaffLeave::count());
        $this->assertSame(18, app(LeaveAllowance::class)->usedDays($staff, 2026, LeaveType::Annual));
    }

    public function test_the_acknowledgement_is_cleared_when_the_form_changes(): void
    {
        $staff = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm', $staff->id)
            ->set('leaveOverageAck', true)
            ->set('returnDate', '2026-08-20')
            ->assertSet('leaveOverageAck', false);
    }

    public function test_an_unlimited_type_never_asks_for_confirmation(): void
    {
        $staff = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm', $staff->id)
            ->set('leaveType', LeaveType::Training->value)
            ->set('firstDay', '2026-08-03')
            ->set('returnDate', '2026-10-30')  // far more than any limit
            ->call('saveLeave')
            ->assertHasNoErrors();

        $this->assertSame(1, StaffLeave::count());
    }

    public function test_editing_a_leave_does_not_count_it_against_itself(): void
    {
        $staff = User::factory()->create();

        // 14 of 15 already — editing the note must not trip the limit.
        $leave = $this->leave($staff, '2026-02-02', '2026-02-20');

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openEditLeave', $leave->id)
            ->set('leaveNote', 'Updated note.')
            ->call('saveLeave')
            ->assertHasNoErrors();

        $this->assertSame('Updated note.', $leave->fresh()->note);
    }

    public function test_changing_the_type_recalculates_the_allowance(): void
    {
        $staff = User::factory()->create();

        // 12 sick days already used.
        $this->leave($staff, '2026-02-02', '2026-02-18', LeaveType::Sick);

        $leave = $this->leave($staff, '2026-08-03', '2026-08-10', LeaveType::Training);

        // Training is unlimited, so this is fine as it stands…
        $component = Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openEditLeave', $leave->id);

        $component->call('saveLeave')->assertHasNoErrors();

        // …but switching it to Sick takes them to 17 of 10.
        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openEditLeave', $leave->id)
            ->set('leaveType', LeaveType::Sick->value)
            ->call('saveLeave')
            ->assertHasErrors('leaveOverageAck');
    }

    public function test_the_live_allowance_line_is_shown_in_the_form(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi']);
        $this->leave($staff, '2026-03-02', '2026-03-09'); // 5 of 15

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm', $staff->id)
            ->set('leaveType', LeaveType::Annual->value)
            ->set('firstDay', '2026-08-03')
            ->set('returnDate', '2026-08-10')
            ->assertSee('5 of 15 used in 2026')
            ->assertSee('this leave uses 5')
            ->assertSee('5 left');
    }

    public function test_the_warning_names_the_person_and_the_year(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi']);
        $this->leave($staff, '2026-02-02', '2026-02-19');

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openLeaveForm', $staff->id)
            ->set('leaveType', LeaveType::Annual->value)
            ->set('firstDay', '2026-08-03')
            ->set('returnDate', '2026-08-10')
            ->assertSee('This takes Kila Vagi to 18 of 15 annual days in 2026')
            ->assertSee('Record anyway');
    }

    // ================= Consistency =================

    public function test_the_staff_tab_and_the_stats_panel_agree(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi']);

        $this->leave($staff, '2026-03-02', '2026-03-09');
        $this->leave($staff, '2026-04-06', '2026-04-09', LeaveType::Sick);

        $component = Livewire::actingAs($this->admin())->test(StaffAvailabilityPage::class);

        $fromStats = collect($component->viewData('stats')['rows'])
            ->firstWhere(fn (array $row) => $row['user']->id === $staff->id)['stats']['total'];

        $component->call('setTab', 'staff');
        $fromStaffTab = $component->viewData('staffYearDays')[$staff->id];

        $this->assertSame(8, $fromStats);
        $this->assertSame($fromStats, $fromStaffTab);
    }

    public function test_the_detail_sheet_agrees_with_the_stats_panel(): void
    {
        $staff = User::factory()->create();

        $this->leave($staff, '2026-03-02', '2026-03-09');
        $this->leave($staff, '2026-04-06', '2026-04-09', LeaveType::Sick);

        $component = Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openStaffDetail', $staff->id);

        $allowance = $component->viewData('detailAllowance');

        $this->assertSame(5, $allowance['annual']['used']);
        $this->assertSame(10, $allowance['annual']['remaining']);
        $this->assertSame(3, $allowance['sick']['used']);

        $fromStats = collect($component->viewData('stats')['rows'])
            ->firstWhere(fn (array $row) => $row['user']->id === $staff->id)['stats']['byType'];

        $this->assertSame($fromStats['annual']['used'], $allowance['annual']['used']);
        $this->assertSame($fromStats['sick']['used'], $allowance['sick']['used']);
    }

    public function test_the_detail_sheet_follows_the_selected_year(): void
    {
        $staff = User::factory()->create();

        $this->leave($staff, '2026-03-02', '2026-03-09');  // 5 in 2026
        $this->leave($staff, '2025-03-03', '2025-03-24');  // 15 in 2025

        $component = Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openStaffDetail', $staff->id);

        $this->assertSame(5, $component->viewData('detailAllowance')['annual']['used']);

        $component->call('previousStatsYear');

        $this->assertSame(15, $component->viewData('detailAllowance')['annual']['used']);
        $this->assertSame(0, $component->viewData('detailAllowance')['annual']['remaining']);
    }

    // ================= Access =================

    public function test_plain_staff_cannot_see_the_stats(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(StaffAvailabilityPage::class)
            ->assertForbidden();
    }

    public function test_clinic_bookers_cannot_see_the_stats(): void
    {
        $this->actingAs(User::factory()->booker()->create())
            ->get('/availability')
            ->assertForbidden();
    }

    public function test_leave_types_do_not_leak_to_other_pages(): void
    {
        $staff = User::factory()->clinician()->create(['name' => 'Kila Vagi']);

        StaffLeave::factory()->create([
            'user_id' => $staff->id,
            'type' => LeaveType::Sick,
            'first_day' => '2026-06-15',
            'return_date' => '2026-06-22',
        ]);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Clinic\ClinicSchedule::class)
            ->call('openCreate')
            ->set('appointment_date', '2026-06-16')
            ->set('start_time', '09:00')
            ->set('end_time', '09:30')
            ->assertSee('On leave')
            ->assertDontSee('Sick leave')
            ->assertDontSee('Sick')
            ->assertDontSee('allowance')
            ->assertDontSee('Leave stats');
    }
}
