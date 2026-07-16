<?php

namespace Tests\Feature\Gym;

use App\Livewire\Gym\GymSchedulePage;
use App\Models\GymSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GymScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_the_gym_schedule(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->get('/gym')->assertOk();
    }

    public function test_staff_cannot_create_or_delete_sessions(): void
    {
        $staff = User::factory()->create();
        $schedule = GymSchedule::factory()->create();

        Livewire::actingAs($staff)
            ->test(GymSchedulePage::class)
            ->call('openCreate')
            ->assertForbidden();

        Livewire::actingAs($staff)
            ->test(GymSchedulePage::class)
            ->call('delete', $schedule->id)
            ->assertForbidden();

        $this->assertDatabaseHas('gym_schedules', ['id' => $schedule->id]);
    }

    public function test_admin_can_create_a_session_with_staff_allocation(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(GymSchedulePage::class)
            ->call('openCreate')
            ->set('name', 'Strength Block')
            ->set('client_type', 'national_federation')
            ->set('client_name', 'PNG Weightlifting')
            ->set('studio', '2')
            ->set('start_date', '2026-08-03')
            ->set('end_date', '2026-08-03')
            ->set('start_time', '08:00')
            ->set('end_time', '09:30')
            ->set('recurrence', 'none')
            ->set('staffIds', [(string) $staff->id])
            ->call('save')
            ->assertHasNoErrors();

        $schedule = GymSchedule::where('name', 'Strength Block')->first();

        $this->assertNotNull($schedule);
        $this->assertSame('2', $schedule->studio);
        $this->assertTrue($schedule->staff->contains($staff));
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(GymSchedulePage::class)
            ->call('openCreate')
            ->set('name', 'Bad Times')
            ->set('client_name', 'Client')
            ->set('start_time', '10:00')
            ->set('end_time', '09:00')
            ->call('save')
            ->assertHasErrors('end_time');
    }

    public function test_weekly_recurrence_requires_at_least_one_weekday(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(GymSchedulePage::class)
            ->call('openCreate')
            ->set('name', 'Weekly Session')
            ->set('client_name', 'Client')
            ->set('recurrence', 'weekly')
            ->set('days_of_week', [])
            ->call('save')
            ->assertHasErrors('days_of_week');
    }

    // ---- Recurrence expansion ----

    public function test_one_off_session_occurs_only_on_start_date(): void
    {
        $schedule = GymSchedule::factory()->create([
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
        ]);

        $this->assertTrue($schedule->occursOn(Carbon::parse('2026-08-03')));
        $this->assertFalse($schedule->occursOn(Carbon::parse('2026-08-04')));
        $this->assertFalse($schedule->occursOn(Carbon::parse('2026-08-02')));
    }

    public function test_daily_recurrence_occurs_every_day_including_boundaries(): void
    {
        $schedule = GymSchedule::factory()->daily('2026-08-03', '2026-08-07')->create();

        $this->assertTrue($schedule->occursOn(Carbon::parse('2026-08-03')), 'start boundary');
        $this->assertTrue($schedule->occursOn(Carbon::parse('2026-08-05')), 'middle');
        $this->assertTrue($schedule->occursOn(Carbon::parse('2026-08-07')), 'end boundary');
        $this->assertFalse($schedule->occursOn(Carbon::parse('2026-08-02')), 'before window');
        $this->assertFalse($schedule->occursOn(Carbon::parse('2026-08-08')), 'after window');
    }

    public function test_weekly_recurrence_occurs_only_on_selected_weekdays(): void
    {
        // Mon(1)/Wed(3)/Fri(5) for two months: 3 Aug 2026 (Mon) – 2 Oct 2026 (Fri).
        $schedule = GymSchedule::factory()->weekly('2026-08-03', '2026-10-02', [1, 3, 5])->create();

        $this->assertTrue($schedule->occursOn(Carbon::parse('2026-08-03')), 'Monday');
        $this->assertTrue($schedule->occursOn(Carbon::parse('2026-08-05')), 'Wednesday');
        $this->assertTrue($schedule->occursOn(Carbon::parse('2026-08-07')), 'Friday');
        $this->assertFalse($schedule->occursOn(Carbon::parse('2026-08-04')), 'Tuesday');
        $this->assertFalse($schedule->occursOn(Carbon::parse('2026-08-08')), 'Saturday');
        $this->assertFalse($schedule->occursOn(Carbon::parse('2026-08-09')), 'Sunday');

        // Deep into the series and the end boundary.
        $this->assertTrue($schedule->occursOn(Carbon::parse('2026-09-16')), 'Wednesday mid-September');
        $this->assertTrue($schedule->occursOn(Carbon::parse('2026-10-02')), 'final Friday');
        $this->assertFalse($schedule->occursOn(Carbon::parse('2026-10-05')), 'Monday after end_date');
    }

    public function test_occurrences_on_returns_matching_sessions_with_staff(): void
    {
        $matching = GymSchedule::factory()->daily('2026-08-03', '2026-08-07')->create();
        GymSchedule::factory()->create([
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-04',
        ]); // Different day only.

        $sessions = GymSchedule::occurrencesOn(Carbon::parse('2026-08-05'));

        $this->assertCount(1, $sessions);
        $this->assertTrue($sessions->first()->is($matching));
        $this->assertTrue($sessions->first()->relationLoaded('staff'));
    }

    public function test_overlapping_sessions_in_the_same_studio_both_render_side_by_side(): void
    {
        $staff = User::factory()->create();

        GymSchedule::factory()->create([
            'name' => 'Session A',
            'studio' => '1',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
        ]);
        GymSchedule::factory()->create([
            'name' => 'Session B',
            'studio' => '1',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'start_time' => '09:30:00',
            'end_time' => '11:00:00',
        ]);

        $component = Livewire::actingAs($staff)
            ->test(GymSchedulePage::class)
            ->set('date', '2026-08-03');

        $blocks = $component->viewData('blocks');

        $this->assertCount(2, $blocks);
        // Both blocks share the cluster: two columns, different column indexes.
        $this->assertSame(2, $blocks[0]['columns']);
        $this->assertSame(2, $blocks[1]['columns']);
        $this->assertNotSame($blocks[0]['column'], $blocks[1]['column']);
    }

    public function test_non_overlapping_sessions_get_full_width(): void
    {
        $staff = User::factory()->create();

        GymSchedule::factory()->create([
            'start_date' => '2026-08-03', 'end_date' => '2026-08-03',
            'start_time' => '08:00:00', 'end_time' => '09:00:00',
        ]);
        GymSchedule::factory()->create([
            'start_date' => '2026-08-03', 'end_date' => '2026-08-03',
            'start_time' => '09:00:00', 'end_time' => '10:00:00',
        ]);

        $component = Livewire::actingAs($staff)
            ->test(GymSchedulePage::class)
            ->set('date', '2026-08-03');

        $blocks = $component->viewData('blocks');

        $this->assertCount(2, $blocks);
        $this->assertSame(1, $blocks[0]['columns']);
        $this->assertSame(1, $blocks[1]['columns']);
    }

    public function test_editing_a_series_updates_all_occurrences(): void
    {
        $admin = User::factory()->admin()->create();
        $schedule = GymSchedule::factory()->daily('2026-08-03', '2026-08-07')->create(['name' => 'Old']);

        Livewire::actingAs($admin)
            ->test(GymSchedulePage::class)
            ->call('openEdit', $schedule->id)
            ->set('name', 'Renamed Series')
            ->call('save')
            ->assertHasNoErrors();

        $schedule->refresh();
        $this->assertSame('Renamed Series', $schedule->name);
        // Still occurs across the whole window under the new name.
        $this->assertTrue($schedule->occursOn(Carbon::parse('2026-08-06')));
    }
}
