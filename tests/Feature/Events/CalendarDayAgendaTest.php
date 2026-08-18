<?php

namespace Tests\Feature\Events;

use App\Livewire\Calendar\EventsCalendar;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the phone day-agenda ("2A") behaviour added with the Modernist
 * redesign: the grid carries markers, the tapped day lists its events below.
 */
class CalendarDayAgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_agenda_starts_on_today(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(EventsCalendar::class)
            ->assertSet('selectedDate', today()->toDateString());
    }

    public function test_tapping_a_day_lists_only_that_days_events(): void
    {
        Event::factory()->create([
            'name' => 'Squad Strength Review',
            'starts_at' => today()->setTime(9, 0),
            'ends_at' => today()->setTime(11, 0),
        ]);

        Event::factory()->create([
            'name' => 'Tomorrow Workshop',
            'starts_at' => today()->addDay()->setTime(9, 0),
            'ends_at' => today()->addDay()->setTime(11, 0),
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(EventsCalendar::class)
            ->call('selectDay', today()->toDateString())
            ->assertViewHas('selectedDayEvents', fn ($events) => $events->count() === 1
                && $events->first()->name === 'Squad Strength Review')
            ->call('selectDay', today()->addDay()->toDateString())
            ->assertViewHas('selectedDayEvents', fn ($events) => $events->count() === 1
                && $events->first()->name === 'Tomorrow Workshop');
    }

    public function test_a_multi_day_event_appears_on_every_day_it_spans(): void
    {
        Event::factory()->create([
            'name' => 'Regional Camp',
            'starts_at' => today()->setTime(9, 0),
            'ends_at' => today()->addDays(2)->setTime(17, 0),
        ]);

        $component = Livewire::actingAs(User::factory()->create())
            ->test(EventsCalendar::class);

        foreach ([0, 1, 2] as $offset) {
            $component->call('selectDay', today()->addDays($offset)->toDateString())
                ->assertViewHas('selectedDayEvents', fn ($events) => $events->pluck('name')->contains('Regional Camp'));
        }
    }

    public function test_changing_month_moves_the_agenda_into_that_month(): void
    {
        $component = Livewire::actingAs(User::factory()->create())
            ->test(EventsCalendar::class)
            ->call('nextMonth');

        $selected = $component->get('selectedDate');

        $this->assertSame(
            today()->addMonthNoOverflow()->startOfMonth()->toDateString(),
            $selected,
            'Moving to another month should park the agenda on its first day.'
        );
    }

    public function test_returning_to_today_reselects_today(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(EventsCalendar::class)
            ->call('nextMonth')
            ->call('goToday')
            ->assertSet('selectedDate', today()->toDateString());
    }

    public function test_an_empty_day_reports_no_events(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(EventsCalendar::class)
            ->call('selectDay', today()->toDateString())
            ->assertViewHas('selectedDayEvents', fn ($events) => $events->isEmpty())
            ->assertSee('Nothing scheduled this day.');
    }
}
