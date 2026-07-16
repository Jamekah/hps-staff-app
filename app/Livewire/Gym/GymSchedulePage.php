<?php

namespace App\Livewire\Gym;

use App\Enums\ClientType;
use App\Enums\Recurrence;
use App\Models\GymSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GymSchedulePage extends Component
{
    use AuthorizesRequests;

    /** Timeline window: 07:00–19:00. */
    public const DAY_START_MINUTES = 7 * 60;

    public const DAY_END_MINUTES = 19 * 60;

    public string $date = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    // Form fields
    public string $name = '';

    public string $client_type = 'national_federation';

    public string $client_name = '';

    public string $studio = '1';

    public string $start_date = '';

    public string $end_date = '';

    public string $start_time = '';

    public string $end_time = '';

    public string $recurrence = 'none';

    public array $days_of_week = [];

    public array $staffIds = [];

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    public function previousDay(): void
    {
        $this->date = Carbon::parse($this->date)->subDay()->toDateString();
    }

    public function nextDay(): void
    {
        $this->date = Carbon::parse($this->date)->addDay()->toDateString();
    }

    public function goToday(): void
    {
        $this->date = now()->toDateString();
    }

    public function openCreate(): void
    {
        $this->authorize('create', GymSchedule::class);

        $this->reset(['editingId', 'name', 'client_name', 'days_of_week', 'staffIds']);
        $this->client_type = 'national_federation';
        $this->studio = '1';
        $this->recurrence = 'none';
        $this->start_date = $this->date;
        $this->end_date = $this->date;
        $this->start_time = '09:00';
        $this->end_time = '10:00';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function openEdit(int $scheduleId): void
    {
        $schedule = GymSchedule::with('staff')->findOrFail($scheduleId);
        $this->authorize('update', $schedule);

        $this->editingId = $schedule->id;
        $this->name = $schedule->name;
        $this->client_type = $schedule->client_type->value;
        $this->client_name = $schedule->client_name;
        $this->studio = $schedule->studio;
        $this->start_date = $schedule->start_date->toDateString();
        $this->end_date = $schedule->end_date->toDateString();
        $this->start_time = substr($schedule->start_time, 0, 5);
        $this->end_time = substr($schedule->end_time, 0, 5);
        $this->recurrence = $schedule->recurrence->value;
        $this->days_of_week = array_map('strval', $schedule->days_of_week ?? []);
        $this->staffIds = $schedule->staff->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_type' => ['required', Rule::in(ClientType::values())],
            'client_name' => ['required', 'string', 'max:255'],
            'studio' => ['required', Rule::in(['1', '2'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'recurrence' => ['required', Rule::in(Recurrence::values())],
            'days_of_week' => ['required_if:recurrence,weekly', 'array'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'staffIds' => ['array'],
            'staffIds.*' => [Rule::exists('users', 'id')->where('is_active', true)],
        ], [
            'days_of_week.required_if' => 'Select at least one weekday for a weekly schedule.',
        ]);

        $attributes = [
            'name' => $validated['name'],
            'client_type' => $validated['client_type'],
            'client_name' => $validated['client_name'],
            'studio' => $validated['studio'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'recurrence' => $validated['recurrence'],
            'days_of_week' => $validated['recurrence'] === 'weekly'
                ? array_map('intval', $validated['days_of_week'])
                : null,
        ];

        if ($this->editingId) {
            $schedule = GymSchedule::findOrFail($this->editingId);
            $this->authorize('update', $schedule);
            $schedule->update($attributes);
            session()->flash('status', 'Session updated (whole series).');
        } else {
            $this->authorize('create', GymSchedule::class);
            $schedule = GymSchedule::create([...$attributes, 'created_by' => auth()->id()]);
            session()->flash('status', 'Session created.');
        }

        $schedule->staff()->sync($validated['staffIds'] ?? []);

        $this->showForm = false;
    }

    public function delete(int $scheduleId): void
    {
        $schedule = GymSchedule::findOrFail($scheduleId);
        $this->authorize('delete', $schedule);

        $schedule->delete();

        session()->flash('status', 'Session deleted (whole series).');
    }

    /**
     * Assign overlapping sessions to side-by-side columns within the timeline.
     * Returns [session, top%, height%, columnIndex, columnCount] per block.
     */
    protected function layoutBlocks($sessions): array
    {
        $windowMinutes = self::DAY_END_MINUTES - self::DAY_START_MINUTES;

        $blocks = $sessions->map(function (GymSchedule $session) use ($windowMinutes) {
            $start = max($this->timeToMinutes($session->start_time), self::DAY_START_MINUTES);
            $end = min($this->timeToMinutes($session->end_time), self::DAY_END_MINUTES);
            $end = max($end, $start + 20); // Keep very short/clamped sessions readable.

            return [
                'session' => $session,
                'startMin' => $start,
                'endMin' => $end,
                'top' => ($start - self::DAY_START_MINUTES) / $windowMinutes * 100,
                'height' => ($end - $start) / $windowMinutes * 100,
                'column' => 0,
                'columns' => 1,
            ];
        })->sortBy('startMin')->values()->all();

        // Greedy column assignment within clusters of transitively overlapping blocks.
        $clusterStart = 0;
        $clusterEnd = -1; // Latest end time seen in the current cluster.
        $columnEnds = []; // Per-column latest end time within the cluster.

        foreach ($blocks as $i => $block) {
            if ($block['startMin'] >= $clusterEnd && $columnEnds !== []) {
                // Cluster finished: every block in it shares the same column count.
                $this->finalizeCluster($blocks, $clusterStart, $i - 1, count($columnEnds));
                $clusterStart = $i;
                $columnEnds = [];
            }

            $placed = false;
            foreach ($columnEnds as $column => $end) {
                if ($block['startMin'] >= $end) {
                    $blocks[$i]['column'] = $column;
                    $columnEnds[$column] = $block['endMin'];
                    $placed = true;
                    break;
                }
            }

            if (! $placed) {
                $blocks[$i]['column'] = count($columnEnds);
                $columnEnds[] = $block['endMin'];
            }

            $clusterEnd = max($clusterEnd, $block['endMin']);
        }

        if ($columnEnds !== []) {
            $this->finalizeCluster($blocks, $clusterStart, count($blocks) - 1, count($columnEnds));
        }

        return $blocks;
    }

    private function finalizeCluster(array &$blocks, int $from, int $to, int $columnCount): void
    {
        for ($i = $from; $i <= $to; $i++) {
            $blocks[$i]['columns'] = $columnCount;
        }
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);

        return (int) $hours * 60 + (int) $minutes;
    }

    public function render()
    {
        $day = Carbon::parse($this->date);
        $sessions = GymSchedule::occurrencesOn($day);

        return view('livewire.gym.gym-schedule-page', [
            'day' => $day,
            'blocks' => $this->layoutBlocks($sessions),
            'hours' => range(7, 19),
            'activeStaff' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'weekdays' => [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'],
        ]);
    }
}
