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
 * The page itself: access, the three tabs, and the rule that the leave type
 * is visible here and nowhere else.
 */
class AvailabilityPageTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Access ----------

    public function test_an_admin_reaches_the_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/availability')
            ->assertOk()
            ->assertSee('Staff Availability');
    }

    public function test_the_super_admin_reaches_the_page(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/availability')
            ->assertOk();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/availability')->assertRedirect(route('login'));
    }

    public function test_the_nav_link_appears_only_for_admins(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/calendar')
            ->assertSee('Availability');

        $this->actingAs(User::factory()->booker()->create())
            ->get('/calendar')
            ->assertDontSee('Availability');

        $this->actingAs(User::factory()->create())
            ->get('/calendar')
            ->assertDontSee('Availability');
    }

    // ---------- Overview ----------

    public function test_the_overview_shows_who_is_away_today(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi', 'position' => 'Physiotherapist']);

        StaffLeave::factory()->create([
            'user_id' => $staff->id,
            'type' => LeaveType::Annual,
            'first_day' => today()->subDay()->toDateString(),
            'return_date' => today()->addDays(3)->toDateString(),
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->assertSee('Away today')
            ->assertSee('Kila Vagi')
            ->assertSee('Physiotherapist')
            ->assertSee('Back '.today()->addDays(3)->format('D j M'));
    }

    public function test_the_overview_has_friendly_empty_states(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->assertSee("Everyone's in today", false)
            ->assertSee('Nobody is due back this week')
            ->assertSee('No leave booked in the next 30 days')
            ->assertSee("Nothing clashes with anyone's leave", false);
    }

    public function test_the_overview_lists_upcoming_leave_and_returns(): void
    {
        $returning = User::factory()->create(['name' => 'Back Soon']);
        $upcoming = User::factory()->create(['name' => 'Off Later']);

        StaffLeave::factory()->create([
            'user_id' => $returning->id,
            'first_day' => today()->subDays(4)->toDateString(),
            'return_date' => today()->addDays(2)->toDateString(),
        ]);

        StaffLeave::factory()->create([
            'user_id' => $upcoming->id,
            'first_day' => today()->addDays(10)->toDateString(),
            'return_date' => today()->addDays(15)->toDateString(),
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->assertSee('Back Soon')
            ->assertSee('Off Later');
    }

    public function test_assignment_warnings_list_upcoming_work_inside_someones_leave(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi']);

        $event = Event::factory()->create([
            'name' => 'Squad Review',
            'starts_at' => today()->addDays(3)->setTime(9, 0),
            'ends_at' => today()->addDays(3)->setTime(11, 0),
        ]);
        $event->staff()->attach($staff);

        StaffLeave::factory()->create([
            'user_id' => $staff->id,
            'first_day' => today()->addDays(2)->toDateString(),
            'return_date' => today()->addDays(6)->toDateString(),
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->assertSee('Assignment warnings')
            ->assertSee('Squad Review')
            ->assertSee('Kila Vagi is away');
    }

    public function test_cancelled_leave_produces_no_warnings(): void
    {
        $staff = User::factory()->create();

        $event = Event::factory()->create([
            'name' => 'Squad Review',
            'starts_at' => today()->addDays(3)->setTime(9, 0),
            'ends_at' => today()->addDays(3)->setTime(11, 0),
        ]);
        $event->staff()->attach($staff);

        StaffLeave::factory()->cancelled()->create([
            'user_id' => $staff->id,
            'first_day' => today()->addDays(2)->toDateString(),
            'return_date' => today()->addDays(6)->toDateString(),
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->assertSee("Nothing clashes with anyone's leave", false);
    }

    // ---------- Month ----------

    public function test_the_month_tab_shows_only_staff_with_leave_until_asked_otherwise(): void
    {
        $away = User::factory()->create(['name' => 'Away Person']);
        User::factory()->create(['name' => 'Present Person']);

        StaffLeave::factory()->create([
            'user_id' => $away->id,
            'first_day' => now()->startOfMonth()->addDays(5)->toDateString(),
            'return_date' => now()->startOfMonth()->addDays(9)->toDateString(),
        ]);

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->call('setTab', 'month');

        $component->assertSee('Away Person')->assertDontSee('Present Person');

        $component->set('showEveryone', true)->assertSee('Present Person');
    }

    public function test_the_month_tab_can_be_paged_and_reset_to_today(): void
    {
        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->call('setTab', 'month')
            ->call('nextMonth');

        $this->assertSame(
            now()->startOfMonth()->addMonthNoOverflow()->toDateString(),
            $component->get('monthCursor'),
        );

        $component->call('goToday');

        $this->assertSame(now()->startOfMonth()->toDateString(), $component->get('monthCursor'));
    }

    public function test_the_phone_day_strip_lists_who_is_away_that_day(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi']);

        StaffLeave::factory()->create([
            'user_id' => $staff->id,
            'first_day' => today()->toDateString(),
            'return_date' => today()->addDays(2)->toDateString(),
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->call('setTab', 'month')
            ->call('selectDay', today()->toDateString())
            ->assertSee('Away on '.today()->format('D j M'))
            ->assertSee('Kila Vagi');
    }

    // ---------- Staff tab ----------

    public function test_the_staff_tab_lists_the_roster_with_account_badges(): void
    {
        User::factory()->rosterOnly()->create(['name' => 'Roster Person', 'position' => 'S&C Coach']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->call('setTab', 'staff')
            ->assertSee('Roster Person')
            ->assertSee('S&C Coach')
            ->assertSee('No login');
    }

    public function test_the_staff_tab_totals_working_days_this_year(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi']);

        // Monday to Monday inside this year: 5 working days.
        $monday = now()->startOfYear()->next(\Carbon\Carbon::MONDAY);

        StaffLeave::factory()->create([
            'user_id' => $staff->id,
            'first_day' => $monday->toDateString(),
            'return_date' => $monday->copy()->addWeek()->toDateString(),
        ]);

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->call('setTab', 'staff');

        $this->assertSame(5, $component->viewData('staffYearDays')[$staff->id]);
    }

    public function test_the_staff_tab_can_be_searched(): void
    {
        User::factory()->create(['name' => 'Kila Vagi']);
        User::factory()->create(['name' => 'Tom Bani']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->call('setTab', 'staff')
            ->set('staffSearch', 'Kila')
            ->assertSee('Kila Vagi')
            ->assertDontSee('Tom Bani');
    }

    // ---------- Staff detail ----------

    public function test_the_staff_detail_sheet_shows_history_and_totals(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi', 'position' => 'Physiotherapist']);

        StaffLeave::factory()->create([
            'user_id' => $staff->id,
            'type' => LeaveType::Sick,
            'first_day' => now()->startOfYear()->addMonth()->toDateString(),
            'return_date' => now()->startOfYear()->addMonth()->addDays(3)->toDateString(),
            'note' => 'Flu.',
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->call('openStaffDetail', $staff->id)
            ->assertSee('Physiotherapist')
            ->assertSee('Leave history')
            ->assertSee('Sick leave')
            ->assertSee('Flu.');
    }

    // ---------- The type stays on this page ----------

    public function test_the_leave_type_is_shown_here(): void
    {
        $staff = User::factory()->create();

        StaffLeave::factory()->create([
            'user_id' => $staff->id,
            'type' => LeaveType::DutyTravel,
            'first_day' => today()->toDateString(),
            'return_date' => today()->addDays(3)->toDateString(),
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->assertSee('Duty travel');
    }

    public function test_the_leave_type_is_not_shown_on_the_calendar(): void
    {
        $staff = User::factory()->create(['name' => 'Kila Vagi']);

        $event = Event::factory()->create([
            'starts_at' => today()->addDay()->setTime(9, 0),
            'ends_at' => today()->addDay()->setTime(11, 0),
        ]);
        $event->staff()->attach($staff);

        StaffLeave::factory()->create([
            'user_id' => $staff->id,
            'type' => LeaveType::DutyTravel,
            'first_day' => today()->toDateString(),
            'return_date' => today()->addDays(3)->toDateString(),
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(\App\Livewire\Calendar\EventsCalendar::class)
            ->call('selectEvent', $event->id)
            ->assertSee('Away')
            ->assertDontSee('Duty travel');
    }

    // ---------- Tab choice is remembered ----------

    public function test_the_chosen_tab_is_remembered_for_the_session(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(StaffAvailabilityPage::class)
            ->call('setTab', 'staff')
            ->assertSet('tab', 'staff');

        Livewire::actingAs($admin)
            ->test(StaffAvailabilityPage::class)
            ->assertSet('tab', 'staff');
    }

    public function test_an_unknown_tab_falls_back_to_the_overview(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->call('setTab', 'nonsense')
            ->assertSet('tab', 'overview');
    }
}
