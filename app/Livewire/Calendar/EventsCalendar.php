<?php

namespace App\Livewire\Calendar;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\User;
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
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->year, $this->month)->subMonth();
        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->year, $this->month)->addMonth();
        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function goToday(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
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

        return view('livewire.calendar.events-calendar', [
            'monthLabel' => $monthStart->format('F Y'),
            'days' => $days,
            'upcoming' => $upcoming,
            'selectedEvent' => $selectedEvent,
            'activeStaff' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
