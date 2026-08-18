<?php

namespace Tests\Feature\Gym;

use App\Livewire\Gym\GymSchedulePage;
use App\Models\GymSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the phone-only studio filter, the per-studio counts, and the "now"
 * marker introduced with the Modernist redesign.
 */
class GymStudioFilterTest extends TestCase
{
    use RefreshDatabase;

    private function sessionInStudio(string $studio, string $name, string $start = '09:00', string $end = '10:00'): GymSchedule
    {
        return GymSchedule::factory()->create([
            'name' => $name,
            'studio' => $studio,
            'start_date' => today(),
            'end_date' => today(),
            'start_time' => $start,
            'end_time' => $end,
            'recurrence' => 'none',
        ]);
    }

    public function test_filter_defaults_to_all_and_lists_every_studio(): void
    {
        $this->sessionInStudio('1', 'Speed Work');
        $this->sessionInStudio('2', 'Netball Conditioning');

        Livewire::actingAs(User::factory()->create())
            ->test(GymSchedulePage::class)
            ->assertSet('studioFilter', 'all')
            ->assertSee('Speed Work')
            ->assertSee('Netball Conditioning');
    }

    /**
     * The filter governs the phone list (`mobileRows`) only — the desktop
     * timeline keeps every studio, so assert on the list data rather than the
     * rendered HTML, which contains both views at once.
     */
    public function test_filtering_to_studio_one_hides_studio_two_sessions(): void
    {
        $this->sessionInStudio('1', 'Speed Work');
        $this->sessionInStudio('2', 'Netball Conditioning');

        Livewire::actingAs(User::factory()->create())
            ->test(GymSchedulePage::class)
            ->set('studioFilter', '1')
            ->assertViewHas('mobileRows', function ($rows) {
                $names = $rows->flatMap(fn ($row) => $row['sessions']->pluck('name'));

                return $names->contains('Speed Work') && ! $names->contains('Netball Conditioning');
            });
    }

    public function test_filtering_to_studio_two_hides_studio_one_sessions(): void
    {
        $this->sessionInStudio('1', 'Speed Work');
        $this->sessionInStudio('2', 'Netball Conditioning');

        Livewire::actingAs(User::factory()->create())
            ->test(GymSchedulePage::class)
            ->set('studioFilter', '2')
            ->assertViewHas('mobileRows', function ($rows) {
                $names = $rows->flatMap(fn ($row) => $row['sessions']->pluck('name'));

                return $names->contains('Netball Conditioning') && ! $names->contains('Speed Work');
            });
    }

    public function test_the_timeline_always_shows_every_studio_regardless_of_filter(): void
    {
        $this->sessionInStudio('1', 'Speed Work');
        $this->sessionInStudio('2', 'Netball Conditioning');

        // The filter is a phone affordance; the desktop timeline (blocks) must
        // keep rendering both studios so overlaps stay visible to admins.
        Livewire::actingAs(User::factory()->create())
            ->test(GymSchedulePage::class)
            ->set('studioFilter', '1')
            ->assertViewHas('blocks', fn (array $blocks) => count($blocks) === 2);
    }

    public function test_per_studio_counts_are_reported(): void
    {
        $this->sessionInStudio('1', 'Speed Work', '07:00', '08:00');
        $this->sessionInStudio('1', 'Elite Strength', '08:00', '09:00');
        $this->sessionInStudio('2', 'Netball Conditioning');

        Livewire::actingAs(User::factory()->create())
            ->test(GymSchedulePage::class)
            ->assertViewHas('studio1Count', 2)
            ->assertViewHas('studio2Count', 1);
    }

    public function test_sessions_sharing_a_start_time_group_under_one_row(): void
    {
        $this->sessionInStudio('1', 'Speed Work', '09:00', '10:00');
        $this->sessionInStudio('2', 'Netball Conditioning', '09:00', '10:30');

        Livewire::actingAs(User::factory()->create())
            ->test(GymSchedulePage::class)
            ->assertViewHas('mobileRows', function ($rows) {
                return $rows->count() === 1
                    && $rows->first()['start'] === '09:00'
                    && $rows->first()['sessions']->count() === 2;
            });
    }

    public function test_now_marker_is_absent_on_days_that_are_not_today(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(GymSchedulePage::class)
            ->set('date', today()->addDay()->toDateString())
            ->assertViewHas('nowOffset', null);
    }

    public function test_tapping_a_session_opens_its_detail_sheet(): void
    {
        $session = $this->sessionInStudio('2', 'Boxing Conditioning');

        Livewire::actingAs(User::factory()->create())
            ->test(GymSchedulePage::class)
            ->call('selectSession', $session->id)
            ->assertSet('selectedScheduleId', $session->id)
            ->assertSee('Allocated staff')
            ->call('closeSheet')
            ->assertSet('selectedScheduleId', null);
    }

    public function test_staff_see_no_edit_controls_in_the_session_sheet(): void
    {
        $session = $this->sessionInStudio('1', 'Speed Work');

        Livewire::actingAs(User::factory()->create())
            ->test(GymSchedulePage::class)
            ->call('selectSession', $session->id)
            ->assertDontSee('Edit Session')
            ->assertDontSee('Delete');
    }

    public function test_admins_see_edit_controls_in_the_session_sheet(): void
    {
        $session = $this->sessionInStudio('1', 'Speed Work');

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(GymSchedulePage::class)
            ->call('selectSession', $session->id)
            ->assertSee('Edit Session');
    }
}
