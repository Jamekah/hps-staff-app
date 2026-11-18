<?php

namespace App\Livewire\Availability;

use App\Enums\LeaveType;
use App\Enums\Role;
use App\Models\ClinicAppointment;
use App\Models\Event;
use App\Models\GymSchedule;
use App\Models\StaffLeave;
use App\Models\User;
use App\Services\StaffAvailability;
use App\Services\WorkingDays;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Staff Availability — who is away, when they're back, and the roster behind it.
 *
 * Admin-only (the `manage-availability` gate). Leave type and notes live here
 * and nowhere else: every other page says "On leave" or "Away" with no reason.
 */
#[Layout('layouts.app')]
class StaffAvailabilityPage extends Component
{
    use AuthorizesRequests, WithFileUploads;

    /** How far ahead the Overview looks for upcoming leave and warnings. */
    public const LOOKAHEAD_DAYS = 30;

    public const IMPORT_MAX_ROWS = 300;

    /** 'overview' | 'month' | 'staff' — remembered for the session. */
    #[Session(key: 'availability.tab')]
    public string $tab = 'overview';

    // ---- Month tab ----
    public string $monthCursor = '';

    /** Phone day-strip anchor, and the day whose absences are listed. */
    public string $weekCursor = '';

    public string $selectedDay = '';

    public bool $showEveryone = false;

    public string $positionFilter = '';

    // ---- Staff tab ----
    public string $staffSearch = '';

    public string $staffPositionFilter = '';

    // ---- Record leave sheet ----
    public bool $showLeaveForm = false;

    public ?int $editingLeaveId = null;

    public ?int $leaveUserId = null;

    public string $leaveStaffSearch = '';

    public string $leaveType = 'annual';

    public string $firstDay = '';

    public string $returnDate = '';

    public string $leaveNote = '';

    // ---- Staff detail sheet ----
    public ?int $detailUserId = null;

    public bool $detailAllYears = false;

    // ---- Add / edit roster staff sheet ----
    public bool $showStaffForm = false;

    public ?int $editingStaffId = null;

    public string $staffName = '';

    public string $staffPosition = '';

    public string $staffEmail = '';

    // ---- Import sheet ----
    public bool $showImport = false;

    public string $importMode = 'paste';

    public string $importText = '';

    public $importFile = null;

    /** @var array<int, array{name: string, position: ?string, email: ?string, status: string, reason: ?string}> */
    public array $importPreview = [];

    public ?array $importResult = null;

    public function mount(): void
    {
        $this->authorize('manage-availability');

        $this->monthCursor = now()->startOfMonth()->toDateString();
        $this->weekCursor = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->selectedDay = now()->toDateString();
    }

    // ================= Navigation =================

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['overview', 'month', 'staff'], true) ? $tab : 'overview';
    }

    public function previousMonth(): void
    {
        $this->monthCursor = Carbon::parse($this->monthCursor)->subMonthNoOverflow()->startOfMonth()->toDateString();
    }

    public function nextMonth(): void
    {
        $this->monthCursor = Carbon::parse($this->monthCursor)->addMonthNoOverflow()->startOfMonth()->toDateString();
    }

    public function goToday(): void
    {
        $this->monthCursor = now()->startOfMonth()->toDateString();
        $this->weekCursor = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->selectedDay = now()->toDateString();
    }

    public function previousWeek(): void
    {
        $this->weekCursor = Carbon::parse($this->weekCursor)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekCursor = Carbon::parse($this->weekCursor)->addWeek()->toDateString();
    }

    public function selectDay(string $date): void
    {
        $this->selectedDay = Carbon::parse($date)->toDateString();
    }

    // ================= Record leave =================

    public function openLeaveForm(?int $userId = null): void
    {
        $this->authorize('create', StaffLeave::class);

        $this->reset(['editingLeaveId', 'leaveStaffSearch', 'leaveNote']);
        $this->leaveUserId = $userId;
        $this->leaveType = LeaveType::Annual->value;
        $this->firstDay = now()->toDateString();
        $this->returnDate = now()->addDay()->toDateString();
        $this->detailUserId = null;
        $this->resetValidation();
        $this->showLeaveForm = true;
    }

    public function openEditLeave(int $leaveId): void
    {
        $leave = StaffLeave::findOrFail($leaveId);
        $this->authorize('update', $leave);

        $this->editingLeaveId = $leave->id;
        $this->leaveUserId = $leave->user_id;
        $this->leaveType = $leave->type->value;
        $this->firstDay = $leave->first_day->toDateString();
        $this->returnDate = $leave->return_date->toDateString();
        $this->leaveNote = $leave->note ?? '';
        $this->leaveStaffSearch = '';
        $this->detailUserId = null;
        $this->resetValidation();
        $this->showLeaveForm = true;
    }

    public function selectLeaveStaff(int $userId): void
    {
        $this->leaveUserId = $userId;
        $this->leaveStaffSearch = '';
    }

    /** Quick chips: +1 day, +1 week, +2 weeks from the first day away. */
    public function setReturnOffset(int $days): void
    {
        if (! $this->firstDay) {
            return;
        }

        $this->returnDate = Carbon::parse($this->firstDay)->addDays($days)->toDateString();
    }

    /** Keep the return date after the first day whenever the first day moves. */
    public function updatedFirstDay(): void
    {
        if (! $this->firstDay) {
            return;
        }

        if (! $this->returnDate || $this->returnDate <= $this->firstDay) {
            $this->returnDate = Carbon::parse($this->firstDay)->addDay()->toDateString();
        }
    }

    public function saveLeave(): void
    {
        $this->authorize('create', StaffLeave::class);

        $validated = $this->validate([
            'leaveUserId' => ['required', Rule::exists('users', 'id')->where('is_active', true)],
            'leaveType' => ['required', Rule::in(LeaveType::values())],
            'firstDay' => ['required', 'date'],
            'returnDate' => ['required', 'date', 'after:firstDay'],
            'leaveNote' => ['nullable', 'string', 'max:2000'],
        ], [
            'leaveUserId.required' => 'Choose a staff member.',
            'leaveUserId.exists' => 'That staff member is not active.',
            'returnDate.after' => 'The return date must be at least one day after the first day away.',
        ]);

        // Nobody is on two kinds of leave at once. The message names the record
        // in the way, so the admin can go and edit that one instead.
        $clash = StaffLeave::query()
            ->where('user_id', $validated['leaveUserId'])
            ->active()
            ->when($this->editingLeaveId, fn ($q) => $q->whereKeyNot($this->editingLeaveId))
            ->overlapping(
                Carbon::parse($validated['firstDay'])->startOfDay(),
                Carbon::parse($validated['returnDate'])->startOfDay(),
            )
            ->first();

        if ($clash) {
            $this->addError('firstDay', sprintf(
                'Overlaps existing %s from %s to %s (back %s).',
                $clash->type->label(),
                $clash->first_day->format('j M'),
                $clash->lastDay()->format('j M'),
                $clash->return_date->format('j M'),
            ));

            return;
        }

        if ($this->editingLeaveId) {
            $leave = StaffLeave::findOrFail($this->editingLeaveId);
            $this->authorize('update', $leave);

            $leave->update([
                'user_id' => $validated['leaveUserId'],
                'type' => $validated['leaveType'],
                'first_day' => $validated['firstDay'],
                'return_date' => $validated['returnDate'],
                'note' => $validated['leaveNote'] ?: null,
            ]);

            session()->flash('status', 'Leave updated.');
        } else {
            $leave = StaffLeave::create([
                'user_id' => $validated['leaveUserId'],
                'type' => $validated['leaveType'],
                'first_day' => $validated['firstDay'],
                'return_date' => $validated['returnDate'],
                'note' => $validated['leaveNote'] ?: null,
                'recorded_by' => auth()->id(),
            ]);

            session()->flash('status', $leave->user->name.' recorded as away, back '.$leave->return_date->format('D j M').'.');
        }

        $this->showLeaveForm = false;
        $this->editingLeaveId = null;
    }

    public function cancelLeave(int $leaveId): void
    {
        $leave = StaffLeave::findOrFail($leaveId);
        $this->authorize('cancel', $leave);

        $leave->update([
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
        ]);

        session()->flash('status', 'Leave cancelled. The record is kept as history.');
    }

    // ================= Staff detail =================

    public function openStaffDetail(int $userId): void
    {
        $this->authorize('manage-availability');

        $this->detailUserId = $userId;
        $this->detailAllYears = false;
    }

    public function closeDetail(): void
    {
        $this->detailUserId = null;
    }

    // ================= Roster staff =================

    public function openStaffForm(?int $userId = null): void
    {
        $this->authorize('manage-roster');

        $this->reset(['editingStaffId', 'staffName', 'staffPosition', 'staffEmail']);

        if ($userId) {
            $user = User::findOrFail($userId);

            // An admin may only edit roster entries. The policy decides; the UI
            // never offers the action for someone who can sign in.
            $this->authorize('update', $user);

            $this->editingStaffId = $user->id;
            $this->staffName = $user->name;
            $this->staffPosition = $user->position ?? '';
            $this->staffEmail = $user->email ?? '';
        }

        $this->detailUserId = null;
        $this->resetValidation();
        $this->showStaffForm = true;
    }

    public function saveStaff(): void
    {
        $this->authorize('manage-roster');

        $validated = $this->validate([
            'staffName' => ['required', 'string', 'max:255'],
            'staffPosition' => ['nullable', 'string', 'max:100'],
            'staffEmail' => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->editingStaffId),
            ],
        ], [
            'staffEmail.unique' => 'Another user already has that email.',
        ]);

        if ($this->editingStaffId) {
            $user = User::findOrFail($this->editingStaffId);
            $this->authorize('update', $user);

            $user->update([
                'name' => $validated['staffName'],
                'position' => $validated['staffPosition'] ?: null,
                'email' => $validated['staffEmail'] ?: null,
            ]);

            session()->flash('status', $user->name.' updated.');
        } else {
            $this->authorize('create', User::class);

            $user = User::create([
                'name' => $validated['staffName'],
                'position' => $validated['staffPosition'] ?: null,
                'email' => $validated['staffEmail'] ?: null,
                // Roster entries have no way in. The password is a throwaway
                // only because the column is NOT NULL.
                'password' => Str::password(32),
                'role' => Role::Staff,
                'is_active' => true,
                'login_enabled' => false,
            ]);

            session()->flash('status', $user->name.' added to the roster.');
        }

        $this->showStaffForm = false;
    }

    public function toggleStaffActive(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('toggleActive', $user);

        $user->update(['is_active' => ! $user->is_active]);

        session()->flash('status', $user->is_active
            ? $user->name.' reactivated.'
            : $user->name.' deactivated.');
    }

    // ================= Import =================

    public function openImport(): void
    {
        $this->authorize('manage-roster');

        $this->reset(['importText', 'importFile', 'importPreview', 'importResult']);
        $this->importMode = 'paste';
        $this->resetValidation();
        $this->showImport = true;
    }

    public function setImportMode(string $mode): void
    {
        $this->importMode = $mode === 'file' ? 'file' : 'paste';
        $this->importPreview = [];
        $this->importResult = null;
        $this->resetValidation();
    }

    public function previewImport(): void
    {
        $this->authorize('manage-roster');

        $this->importResult = null;

        $rows = $this->importMode === 'file'
            ? $this->rowsFromFile()
            : $this->rowsFromText();

        if ($rows === null) {
            return;
        }

        if ($rows === []) {
            $this->addError($this->importMode === 'file' ? 'importFile' : 'importText', 'Nothing to import — add at least one row.');

            return;
        }

        if (count($rows) > self::IMPORT_MAX_ROWS) {
            $this->addError($this->importMode === 'file' ? 'importFile' : 'importText',
                'Too many rows: '.count($rows).'. The limit is '.self::IMPORT_MAX_ROWS.'.');

            return;
        }

        $this->importPreview = $this->classify($rows);
    }

    /**
     * One row per non-blank line: `Name, Position, Email`, the last two optional.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>|null
     */
    protected function rowsFromText(): ?array
    {
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $this->importText) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = array_map('trim', explode(',', $line));

            $rows[] = [$parts[0] ?? '', $parts[1] ?? '', $parts[2] ?? ''];
        }

        return $rows;
    }

    /** @return array<int, array{0: string, 1: string, 2: string}>|null */
    protected function rowsFromFile(): ?array
    {
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ], [
            'importFile.required' => 'Choose a CSV file.',
            'importFile.max' => 'The file must be 1MB or smaller.',
        ]);

        $handle = fopen($this->importFile->getRealPath(), 'r');

        if ($handle === false) {
            $this->addError('importFile', 'That file could not be read.');

            return null;
        }

        $rows = [];
        $first = true;

        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $cells = array_map(fn ($cell) => trim((string) $cell), $data);

            if ($cells === [] || implode('', $cells) === '') {
                continue;
            }

            // Skip the template's header row if it is present.
            if ($first) {
                $first = false;

                if (strtolower($cells[0] ?? '') === 'name') {
                    continue;
                }
            }

            $rows[] = [$cells[0] ?? '', $cells[1] ?? '', $cells[2] ?? ''];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Mark each row New, Duplicate or Invalid.
     *
     * Duplicates match on name (case-insensitive, trimmed) or email, against
     * both the existing users and the rows already accepted in this batch —
     * pasting the same person twice should not create them twice.
     *
     * @param  array<int, array{0: string, 1: string, 2: string}>  $rows
     */
    protected function classify(array $rows): array
    {
        $existingNames = User::query()->pluck('name')
            ->map(fn (string $name) => mb_strtolower(trim($name)))
            ->all();

        $existingEmails = User::query()->whereNotNull('email')->pluck('email')
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->all();

        $preview = [];
        $seenNames = [];
        $seenEmails = [];

        foreach ($rows as [$name, $position, $email]) {
            $name = trim($name);
            $position = trim($position);
            $email = mb_strtolower(trim($email));

            $row = [
                'name' => $name,
                'position' => $position ?: null,
                'email' => $email ?: null,
                'status' => 'new',
                'reason' => null,
            ];

            if ($name === '') {
                $row['status'] = 'invalid';
                $row['reason'] = 'Missing name';
            } elseif ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $row['status'] = 'invalid';
                $row['reason'] = 'Invalid email';
            } elseif (in_array(mb_strtolower($name), $existingNames, true) || in_array(mb_strtolower($name), $seenNames, true)) {
                $row['status'] = 'duplicate';
                $row['reason'] = 'Name already on the roster';
            } elseif ($email !== '' && (in_array($email, $existingEmails, true) || in_array($email, $seenEmails, true))) {
                $row['status'] = 'duplicate';
                $row['reason'] = 'Email already in use';
            }

            if ($row['status'] === 'new') {
                $seenNames[] = mb_strtolower($name);

                if ($email !== '') {
                    $seenEmails[] = $email;
                }
            }

            $preview[] = $row;
        }

        return $preview;
    }

    public function confirmImport(): void
    {
        $this->authorize('manage-roster');
        $this->authorize('create', User::class);

        $created = 0;

        foreach ($this->importPreview as $row) {
            if ($row['status'] !== 'new') {
                continue;
            }

            User::create([
                'name' => $row['name'],
                'position' => $row['position'],
                'email' => $row['email'],
                'password' => Str::password(32),
                'role' => Role::Staff,
                'is_active' => true,
                'login_enabled' => false,
            ]);

            $created++;
        }

        $this->importResult = [
            'created' => $created,
            'duplicates' => collect($this->importPreview)->where('status', 'duplicate')->count(),
            'invalid' => collect($this->importPreview)->where('status', 'invalid')->count(),
        ];

        $this->importPreview = [];
        $this->importText = '';
        $this->importFile = null;
    }

    /** The template CSV, so nobody has to guess the column order. */
    public function downloadTemplate()
    {
        $this->authorize('manage-roster');

        $csv = "name,position,email\nJane Kila,Physiotherapist,jane.kila@example.com\nTom Bani,S&C Coach,\n";

        return response()->streamDownload(
            fn () => print ($csv),
            'hps-staff-import-template.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    // ================= Data for the view =================

    /**
     * Everything the chosen person is already committed to during the leave
     * window — events, gym occurrences and scheduled clinic appointments.
     * Shown live while recording, and never acted on automatically.
     */
    protected function leaveConflictReport(): array
    {
        if (! $this->leaveUserId || ! $this->firstDay || ! $this->returnDate) {
            return [];
        }

        try {
            $start = Carbon::parse($this->firstDay)->startOfDay();
            $end = Carbon::parse($this->returnDate)->startOfDay();
        } catch (\Throwable) {
            return [];
        }

        if ($end->lte($start)) {
            return [];
        }

        return app(StaffAvailability::class)
            ->assignmentConflicts($this->leaveUserId, $start, $end)
            ->sortBy(fn (array $row) => $row['starts_at'])
            ->values()
            ->all();
    }

    /**
     * Upcoming commitments where the assigned person is on leave.
     *
     * Gym series are expanded in PHP from a single query rather than queried
     * per day, so a 30-day lookahead stays cheap.
     */
    protected function assignmentWarnings(): array
    {
        $from = now()->startOfDay();
        $to = now()->copy()->addDays(self::LOOKAHEAD_DAYS)->endOfDay();

        $leaves = StaffLeave::query()
            ->active()
            ->overlapping($from, $to)
            ->with('user')
            ->get();

        if ($leaves->isEmpty()) {
            return [];
        }

        $byUser = $leaves->groupBy('user_id');
        $userIds = $byUser->keys()->all();
        $warnings = [];

        $coveringLeave = function (int $userId, Carbon $moment) use ($byUser): ?StaffLeave {
            foreach ($byUser->get($userId, collect()) as $leave) {
                if ($leave->coversDate($moment)) {
                    return $leave;
                }
            }

            return null;
        };

        // ---- Events ----
        $events = Event::query()
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->whereHas('staff', fn ($q) => $q->whereIn('users.id', $userIds))
            ->with('staff')
            ->orderBy('starts_at')
            ->get();

        foreach ($events as $event) {
            foreach ($event->staff as $member) {
                $leave = $coveringLeave($member->id, $event->starts_at->copy());

                if ($leave) {
                    $warnings[] = [
                        'type' => 'event',
                        'when' => $event->starts_at->copy(),
                        'title' => $event->name,
                        'person' => $member->name,
                        'back' => $leave->return_date->copy(),
                        'link' => route('calendar'),
                    ];
                }
            }
        }

        // ---- Gym occurrences ----
        $series = GymSchedule::query()
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->whereHas('staff', fn ($q) => $q->whereIn('users.id', $userIds))
            ->with('staff')
            ->get();

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            foreach ($series as $session) {
                if (! $session->occursOn($day)) {
                    continue;
                }

                foreach ($session->staff as $member) {
                    $leave = $coveringLeave($member->id, $day->copy());

                    if ($leave) {
                        $warnings[] = [
                            'type' => 'gym',
                            'when' => $day->copy()->setTimeFromTimeString($session->start_time),
                            'title' => $session->name.' (Studio '.$session->studio.')',
                            'person' => $member->name,
                            'back' => $leave->return_date->copy(),
                            'link' => route('gym'),
                        ];
                    }
                }
            }
        }

        // ---- Clinic appointments ----
        $appointments = ClinicAppointment::query()
            ->blocking()
            ->whereIn('assigned_staff_id', $userIds)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->with(['client', 'service', 'clinician'])
            ->orderBy('starts_at')
            ->get();

        foreach ($appointments as $appointment) {
            $leave = $coveringLeave($appointment->assigned_staff_id, $appointment->starts_at->copy());

            if ($leave) {
                $warnings[] = [
                    'type' => 'clinic',
                    'when' => $appointment->starts_at->copy(),
                    'title' => ($appointment->client?->name ?? 'Client').' — '.($appointment->service?->name ?? 'appointment'),
                    'person' => $appointment->clinician?->name ?? '—',
                    'back' => $leave->return_date->copy(),
                    'link' => route('clinic'),
                ];
            }
        }

        usort($warnings, fn (array $a, array $b) => $a['when'] <=> $b['when']);

        return $warnings;
    }

    /** Staff rows and their leave bars for the desktop month grid. */
    protected function monthGrid(Carbon $monthStart): array
    {
        $monthEnd = $monthStart->copy()->endOfMonth();

        $leaves = StaffLeave::query()
            ->active()
            ->overlapping($monthStart->copy()->startOfDay(), $monthEnd->copy()->endOfDay())
            ->get()
            ->groupBy('user_id');

        $staff = User::query()
            ->where('is_active', true)
            ->when($this->positionFilter, fn ($q) => $q->where('position', $this->positionFilter))
            ->when(! $this->showEveryone, fn ($q) => $q->whereIn('id', $leaves->keys()->all() ?: [0]))
            ->orderBy('name')
            ->get();

        $days = [];
        for ($day = $monthStart->copy(); $day->lte($monthEnd); $day->addDay()) {
            $days[] = $day->copy();
        }

        $rows = [];

        foreach ($staff as $member) {
            $cells = [];

            foreach ($days as $day) {
                $leave = $leaves->get($member->id, collect())
                    ->first(fn (StaffLeave $l) => $l->coversDate($day));

                $cells[] = [
                    'date' => $day,
                    'leave' => $leave,
                    'isWeekend' => $day->isWeekend(),
                    'isToday' => $day->isToday(),
                    'startsHere' => $leave && $leave->first_day->isSameDay($day),
                    'endsHere' => $leave && $leave->lastDay()->isSameDay($day),
                ];
            }

            $rows[] = ['user' => $member, 'cells' => $cells];
        }

        return ['days' => $days, 'rows' => $rows];
    }

    /**
     * Working days of leave taken this calendar year, per user id. Leave that
     * straddles new year is clipped, so it counts once in each year.
     *
     * @param  Collection<int, User>  $users
     * @return array<int, int>
     */
    protected function workingDaysThisYear(Collection $users): array
    {
        $yearStart = now()->startOfYear()->startOfDay();
        $nextYearStart = now()->startOfYear()->addYear();

        $leaves = StaffLeave::query()
            ->whereIn('user_id', $users->pluck('id')->all() ?: [0])
            ->active()
            ->overlapping($yearStart, $nextYearStart)
            ->get();

        $totals = [];

        foreach ($leaves as $leave) {
            $from = $leave->first_day->copy()->startOfDay();
            $to = $leave->return_date->copy()->startOfDay();

            if ($from->lt($yearStart)) {
                $from = $yearStart->copy();
            }

            if ($to->gt($nextYearStart)) {
                $to = $nextYearStart->copy();
            }

            $totals[$leave->user_id] = ($totals[$leave->user_id] ?? 0) + WorkingDays::between($from, $to);
        }

        return $totals;
    }

    /** "8 working days (10 calendar) · back Wed 2 Dec", computed live. */
    protected function summaryPreview(): ?array
    {
        if (! $this->firstDay || ! $this->returnDate) {
            return null;
        }

        try {
            $first = Carbon::parse($this->firstDay)->startOfDay();
            $return = Carbon::parse($this->returnDate)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        if ($return->lte($first)) {
            return null;
        }

        return [
            'working' => WorkingDays::between($first, $return),
            'calendar' => (int) $first->diffInDays($return),
            'back' => $return->format('D j M'),
            'lastDay' => $return->copy()->subDay()->format('D j M'),
        ];
    }

    public function render()
    {
        $today = now()->startOfDay();
        $monthStart = Carbon::parse($this->monthCursor)->startOfMonth();

        // ---- Overview panels ----
        $awayToday = StaffLeave::query()
            ->active()
            ->overlapping($today, $today->copy()->addDay())
            ->with('user')
            ->get()
            ->sortBy(fn (StaffLeave $leave) => $leave->user?->name)
            ->values();

        $returningSoon = StaffLeave::query()
            ->active()
            ->whereDate('return_date', '>=', $today->toDateString())
            ->whereDate('return_date', '<=', $today->copy()->addDays(7)->toDateString())
            ->with('user')
            ->orderBy('return_date')
            ->get();

        $upcoming = StaffLeave::query()
            ->active()
            ->whereDate('first_day', '>', $today->toDateString())
            ->whereDate('first_day', '<=', $today->copy()->addDays(self::LOOKAHEAD_DAYS)->toDateString())
            ->with('user')
            ->orderBy('first_day')
            ->get();

        // ---- Phone day strip ----
        $weekStart = Carbon::parse($this->weekCursor)->startOfWeek(Carbon::MONDAY);
        $strip = [];
        for ($i = 0; $i < 7; $i++) {
            $strip[] = $weekStart->copy()->addDays($i);
        }

        $selected = Carbon::parse($this->selectedDay);
        $awayOnSelectedDay = StaffLeave::query()
            ->active()
            ->overlapping($selected->copy()->startOfDay(), $selected->copy()->startOfDay()->addDay())
            ->with('user')
            ->get()
            ->sortBy(fn (StaffLeave $leave) => $leave->user?->name)
            ->values();

        // ---- Staff tab ----
        $staff = User::query()
            ->when($this->staffSearch, fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$this->staffSearch.'%')
                ->orWhere('position', 'like', '%'.$this->staffSearch.'%')))
            ->when($this->staffPositionFilter, fn ($q) => $q->where('position', $this->staffPositionFilter))
            ->orderBy('name')
            ->get();

        $nextLeave = StaffLeave::query()
            ->active()
            ->whereDate('return_date', '>', $today->toDateString())
            ->with('user')
            ->get()
            ->sortBy('first_day')
            ->groupBy('user_id')
            ->map(fn (Collection $group) => $group->first());

        // ---- Staff detail sheet ----
        $detailUser = $this->detailUserId ? User::find($this->detailUserId) : null;
        $detailLeaves = collect();
        $detailTotals = [];

        if ($detailUser) {
            $yearStart = now()->startOfYear()->startOfDay();
            $nextYearStart = now()->startOfYear()->addYear();

            $detailLeaves = StaffLeave::query()
                ->where('user_id', $detailUser->id)
                ->when(! $this->detailAllYears, fn ($q) => $q
                    ->whereDate('return_date', '>', $yearStart->toDateString())
                    ->whereDate('first_day', '<', $nextYearStart->toDateString()))
                ->orderByDesc('first_day')
                ->get();

            foreach ($detailLeaves as $leave) {
                if ($leave->isCancelled()) {
                    continue;
                }

                $from = $leave->first_day->copy()->startOfDay()->max($yearStart);
                $to = $leave->return_date->copy()->startOfDay()->min($nextYearStart);

                if ($to->lte($from)) {
                    continue;
                }

                $key = $leave->type->value;
                $detailTotals[$key] = ($detailTotals[$key] ?? 0) + WorkingDays::between($from, $to);
            }
        }

        return view('livewire.availability.staff-availability-page', [
            'awayToday' => $awayToday,
            'returningSoon' => $returningSoon,
            'upcomingLeave' => $upcoming,
            'warnings' => $this->tab === 'overview' ? $this->assignmentWarnings() : [],
            'monthLabel' => $monthStart->format('F Y'),
            'grid' => $this->tab === 'month' ? $this->monthGrid($monthStart) : ['days' => [], 'rows' => []],
            'strip' => $strip,
            'selectedDate' => $selected,
            'awayOnSelectedDay' => $awayOnSelectedDay,
            'positions' => User::query()->whereNotNull('position')->distinct()->orderBy('position')->pluck('position'),
            'staff' => $staff,
            'staffYearDays' => $this->workingDaysThisYear($staff),
            'nextLeave' => $nextLeave,
            'leaveStaffResults' => User::query()
                ->where('is_active', true)
                ->when($this->leaveStaffSearch, fn ($q) => $q->where('name', 'like', '%'.$this->leaveStaffSearch.'%'))
                ->orderBy('name')
                ->limit(8)
                ->get(),
            'leaveStaffMember' => $this->leaveUserId ? User::find($this->leaveUserId) : null,
            'leaveTypes' => LeaveType::cases(),
            'conflictReport' => $this->showLeaveForm ? $this->leaveConflictReport() : [],
            'detailUser' => $detailUser,
            'detailLeaves' => $detailLeaves,
            'detailTotals' => $detailTotals,
            'summary' => $this->summaryPreview(),
        ]);
    }
}
