<?php

namespace Tests\Feature\Events;

use App\Enums\EventType;
use App\Livewire\Calendar\EventsCalendar;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventsCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_the_calendar(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->get('/calendar')->assertOk();
    }

    public function test_staff_cannot_open_the_create_form(): void
    {
        $staff = User::factory()->create();

        Livewire::actingAs($staff)
            ->test(EventsCalendar::class)
            ->call('openCreate')
            ->assertForbidden();
    }

    public function test_staff_cannot_delete_an_event(): void
    {
        $staff = User::factory()->create();
        $event = Event::factory()->create();

        Livewire::actingAs($staff)
            ->test(EventsCalendar::class)
            ->call('delete', $event->id)
            ->assertForbidden();

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_admin_can_create_an_event_with_staff_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->count(2)->create();

        Livewire::actingAs($admin)
            ->test(EventsCalendar::class)
            ->call('openCreate')
            ->set('name', 'Team Workshop')
            ->set('type', 'internal')
            ->set('details', 'Quarterly planning session.')
            ->set('location', 'HPS Main Hall')
            ->set('starts_at', '2026-08-10T09:00')
            ->set('ends_at', '2026-08-10T12:00')
            ->set('staffIds', $staff->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::where('name', 'Team Workshop')->first();

        $this->assertNotNull($event);
        $this->assertSame(EventType::Internal, $event->type);
        $this->assertSame('HPS Main Hall', $event->location);
        $this->assertEqualsCanonicalizing(
            $staff->pluck('id')->all(),
            $event->staff->pluck('id')->all()
        );
    }

    public function test_inactive_users_cannot_be_assigned_to_an_event(): void
    {
        $admin = User::factory()->admin()->create();
        $inactive = User::factory()->inactive()->create();

        Livewire::actingAs($admin)
            ->test(EventsCalendar::class)
            ->call('openCreate')
            ->set('name', 'Test Event')
            ->set('starts_at', '2026-08-10T09:00')
            ->set('ends_at', '2026-08-10T12:00')
            ->set('staffIds', [(string) $inactive->id])
            ->call('save')
            ->assertHasErrors('staffIds.0');
    }

    public function test_changing_start_pulls_an_earlier_end_along(): void
    {
        $admin = User::factory()->admin()->create();

        $component = Livewire::actingAs($admin)
            ->test(EventsCalendar::class)
            ->call('openCreate')
            ->set('ends_at', '2026-08-10T11:00')
            ->set('starts_at', '2026-08-12T09:00'); // Start moved past the end.

        // End is pulled to the same day, two hours after the new start.
        $this->assertSame('2026-08-12T11:00', $component->get('ends_at'));
    }

    public function test_changing_start_keeps_a_still_valid_end(): void
    {
        $admin = User::factory()->admin()->create();

        $component = Livewire::actingAs($admin)
            ->test(EventsCalendar::class)
            ->call('openCreate')
            ->set('ends_at', '2026-08-20T17:00')
            ->set('starts_at', '2026-08-12T09:00'); // End is still after the start.

        $this->assertSame('2026-08-20T17:00', $component->get('ends_at'));
    }

    public function test_end_must_be_after_start(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(EventsCalendar::class)
            ->call('openCreate')
            ->set('name', 'Backwards Event')
            ->set('starts_at', '2026-08-10T12:00')
            ->set('ends_at', '2026-08-10T09:00')
            ->call('save')
            ->assertHasErrors('ends_at');
    }

    public function test_admin_can_update_and_delete_an_event(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['name' => 'Old Name']);

        Livewire::actingAs($admin)
            ->test(EventsCalendar::class)
            ->call('openEdit', $event->id)
            ->set('name', 'New Name')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('New Name', $event->fresh()->name);

        Livewire::actingAs($admin)
            ->test(EventsCalendar::class)
            ->call('delete', $event->id);

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_multi_day_event_appears_on_each_spanned_day(): void
    {
        $admin = User::factory()->admin()->create();

        Event::factory()->create([
            'name' => 'Training Camp',
            'starts_at' => '2026-08-10 08:00:00',
            'ends_at' => '2026-08-12 17:00:00',
        ]);

        $component = Livewire::actingAs($admin)->test(EventsCalendar::class);
        $component->set('year', 2026)->set('month', 8);

        $days = collect($component->viewData('days'));

        foreach (['2026-08-10', '2026-08-11', '2026-08-12'] as $date) {
            $day = $days->firstWhere(fn ($d) => $d['date']->toDateString() === $date);
            $this->assertTrue(
                $day['events']->contains('name', 'Training Camp'),
                "Event missing on {$date}"
            );
        }

        $before = $days->firstWhere(fn ($d) => $d['date']->toDateString() === '2026-08-09');
        $this->assertFalse($before['events']->contains('name', 'Training Camp'));
    }

    public function test_upcoming_sidebar_lists_events_beyond_the_current_month(): void
    {
        $staff = User::factory()->create();

        Event::factory()->create([
            'name' => 'This Month Event',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
        ]);
        Event::factory()->create([
            'name' => 'Future Event',
            'starts_at' => now()->addMonths(2),
            'ends_at' => now()->addMonths(2)->addHours(2),
        ]);

        $component = Livewire::actingAs($staff)->test(EventsCalendar::class);

        $upcoming = $component->viewData('upcoming');

        $this->assertTrue($upcoming->contains('name', 'Future Event'));
        $this->assertFalse($upcoming->contains('name', 'This Month Event'));
    }
}
