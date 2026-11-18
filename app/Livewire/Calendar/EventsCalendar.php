<?php

namespace App\Livewire\Calendar;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\User;
use App\Services\StaffAvailability;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class EventsCalendar extends Component
{
    use AuthorizesRequests;

    public int $year;

    public int $month;

    public ?int $selectedEventId = null;

    /**
     * The day whose agenda is listed under the grid on phones (Y-m-d).
     * Desktop shows pills in the cells and ignores this.
     */
    public string $selectedDate = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    // Form fields
    public string $name = '';

    public string $type = 'internal';

    public string $details = '';

    public string $location = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public array $staffIds = [];

    public function mount(): void
    {
        $now = now();
        $this->year = $now->year;
        $this->month = $now->month;
        $this->selectedDate = $now->toDateString();
    }

    public function previousMonth(): void
    {
        $this->goToMonth(Carbon::create($this->year, $this->month)->subMonth());
    }

    public function nextMonth(): void
    {
        $this->goToMonth(Carbon::create($this->year, $this->month)->addMonth());
    }

    public function goToday(): void
    {
        $this->goToMonth(now());
        $this->selectedDate = now()->toDateString();
    }

    public function selectDay(string $date): void
    {
        $this->selectedDate = Carbon::parse($date)->toDateString();
    }

    /**
     * Move the grid to a month, keeping the mobile day agenda on a day that
     * actually exists in it (today when it falls inside, otherwise the 1st).
     */
    protected function goToMonth(Carbon $date): void
    {
        $this->year = $date->year;
        $this->month = $date->month;

        $this->selectedDate = now()->year === $this->year && now()->month === $this->month
            ? now()->toDateString()
            : $date->copy()->startOfMonth()->toDateString();
    }

    public function selectEvent(int $eventId): void
    {
        $this->selectedEventId = $eventId;
    }

    public function closeModal(): void
    {
        $this->selectedEventId = null;
    }

    /**
     * Keep the end datetime from landing before the start: whenever the start
     * changes, an empty or now-invalid end is moved to the same day, two
     * hours after the new start.
     */
    public function updatedStartsAt(): void
    {
        try {
            $start = Carbon::parse($this->starts_at);
        } catch (\Throwable) {
            return;
        }

        $endIsInvalid = ! $this->ends_at
            || Carbon::parse($this->ends_at)->lte($start);

        if ($endIsInvalid) {
            $this->ends_at = $start->copy()->addHours(2)->format('Y-m-d\TH:i');
        }
    }

    public function openCreate(?string $date = null): void
    {
        $this->authorize('create', Event::class);

        $this->reset(['editingId', 'name', 'details', 'location', 'staffIds']);
        $this->type = 'internal';
        $start = $date ? Carbon::parse($date)->setTime(9, 0) : now()->addDay()->setTime(9, 0);
        $this->starts_at = $start->format('Y-m-d\TH:i');
        $this->ends_at = $start->copy()->addHours(2)->format('Y-m-d\TH:i');
        $this->resetValidation();
        $this->selectedEventId = null;
        $this->showForm = true;
    }

    public function openEdit(int $eventId): void
    {
        $event = Event::with('staff')->findOrFail($eventId);
        $this->authorize('update', $event);

        $this->editingId = $event->id;
        $this->name = $event->name;
        $this->type = $event->type->value;
        $this->details = $event->details ?? '';
        $this->location = $event->location ?? '';
        $this->starts_at = $event->starts_at->format('Y-m-d\TH:i');
        $this->ends_at = $event->ends_at->format('Y-m-d\TH:i');
        $this->staffIds = $event->staff->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->resetValidation();
        $this->selectedEventId = null;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(EventType::values())],
            'details' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'staffIds' => ['array'],
            'staffIds.*' => [Rule::exists('users', 'id')->where('is_active', true)],
        ]);

        // Staff on leave cannot be *newly* assigned. People already assigned
        // when the leave was recorded stay put and are flagged instead, so an
        // unrelated edit is never blocked by someone else's leave.
        $start = Carbon::parse($validated['starts_at']);
        $end = Carbon::parse($validated['ends_at']);

        $selectedIds = array_map('intval', $validated['staffIds'] ?? []);

        $alreadyAssigned = $this->editingId
            ? Event::findOrFail($this->editingId)->staff()->pluck('users.id')->map(fn ($id) => (int) $id)->all()
            : [];

        $newlyAdded = array_values(array_diff($selectedIds, $alreadyAssigned));

        if ($newlyAdded !== []) {
            $onLeave = app(StaffAvailability::class)->leaveMap($newlyAdded, $start, $end);

            if ($onLeave !== []) {
                $names = User::whereIn('id', array_keys($onLeave))->orderBy('name')->pluck('name')->all();

                $this->addError('staffIds', count($names) === 1
                    ? $names[0].' is on leave during this event and cannot be assigned.'
                    : implode(', ', $names).' are on leave during this event and cannot be assigned.');

                return;
            }
        }

        $attributes = [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'details' => $validated['details'] ?: null,
            'location' => $validated['location'] ?: null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
        ];

        if ($this->editingId) {
            $event = Event::findOrFail($this->editingId);
            $this->authorize('update', $event);
            $event->update($attributes);
        } else {
            $this->authorize('create', Event::class);
            $event = Event::create([...$attributes, 'created_by' => auth()->id()]);
        }

        $event->staff()->sync($validated['staffIds'] ?? []);

        $this->showForm = false;
        session()->flash('status', $this->editingId ? 'Event updated.' : 'Event created.');

        // Keep the calendar on the month of the saved event.
        $start = Carbon::parse($validated['starts_at']);
        $this->year = $start->year;
        $this->month = $start->month;
    }

    public function delete(int $eventId): void
    {
        $event = Event::findOrFail($eventId);
        $this->authorize('delete', $event);

        $event->delete();

        $this->selectedEventId = null;
        session()->flash('status', 'Event deleted.');
    }

    public function render()
    {
        $monthStart = Carbon::create($this->year, $this->month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

        $events = Event::query()
            ->overlapping($gridStart, $gridEnd)
            ->with('staff')
            ->orderBy('starts_at')
            ->get();

        // One entry per day cell; multi-day events appear on every day they span.
        $days = [];
        for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay()) {
            $days[] = [
                'date' => $day->copy(),
                'inMonth' => $day->month === $this->month,
                'isToday' => $day->isToday(),
                'events' => $events->filter(
                    fn (Event $event) => $day->between(
                        $event->starts_at->copy()->startOfDay(),
                        $event->ends_at->copy()->endOfDay()
                    )
                )->values(),
            ];
        }

        $upcoming = Event::query()
            ->where('starts_at', '>', $monthEnd)
            ->with('staff')
            ->orderBy('starts_at')
            ->limit(10)
            ->get();

        $selectedEvent = $this->selectedEventId
            ? Event::with(['staff', 'creator'])->find($this->selectedEventId)
            : null;

        $availability = app(StaffAvailability::class);
        $activeStaff = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'position']);

        // Who is away for any part of the window currently in the form. The
        // picker greys these out and re-evaluates as the dates change.
        $staffOnLeave = [];

        if ($this->showForm && $this->starts_at && $this->ends_at) {
            try {
                $formStart = Carbon::parse($this->starts_at);
                $formEnd = Carbon::parse($this->ends_at);

                if ($formEnd->gt($formStart)) {
                    $staffOnLeave = $availability->leaveMap($activeStaff->pluck('id')->all(), $formStart, $formEnd);
                }
            } catch (\Throwable) {
                $staffOnLeave = [];
            }
        }

        // "Away" badges on the detail sheet — no reason shown, ever.
        $selectedEventLeave = $selectedEvent
            ? $availability->leaveMap(
                $selectedEvent->staff->pluck('id')->all(),
                $selectedEvent->starts_at,
                $selectedEvent->ends_at,
            )
            : [];

        // The mobile agenda: events on the tapped day, drawn from the same
        // per-cell collection the grid uses so the two can never disagree.
        $selected = $this->selectedDate ? Carbon::parse($this->selectedDate) : null;
        $selectedDay = $selected
            ? collect($days)->firstWhere(fn (array $day) => $day['date']->isSameDay($selected))
            : null;

        return view('livewire.calendar.events-calendar', [
            'monthLabel' => $monthStart->format('F Y'),
            'days' => $days,
            'upcoming' => $upcoming,
            'selectedEvent' => $selectedEvent,
            'selectedDayDate' => $selected,
            'selectedDayEvents' => $selectedDay['events'] ?? collect(),
            'activeStaff' => $activeStaff,
            'staffOnLeave' => $staffOnLeave,
            'selectedEventLeave' => $selectedEventLeave,
        ]);
    }
}
