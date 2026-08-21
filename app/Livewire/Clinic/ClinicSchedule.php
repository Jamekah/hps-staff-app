<?php

namespace App\Livewire\Clinic;

use App\Enums\AppointmentStatus;
use App\Enums\Recurrence;
use App\Models\ClinicAppointment;
use App\Models\ClinicClient;
use App\Models\ClinicService;
use App\Services\ClinicBooking;
use App\Services\ClinicClashException;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Component;

/**
 * The full SM Clinic module: daily timeline, monthly overview, booking form,
 * and the client database. Restricted to admins and staff with can_book.
 */
#[Layout('layouts.app')]
class ClinicSchedule extends Component
{
    use AuthorizesRequests;

    /** Clinic day runs 08:00–16:00. */
    public const DAY_START_MINUTES = 8 * 60;

    public const DAY_END_MINUTES = 16 * 60;

    public string $date = '';

    /** 'day' or 'month' — remembered for the session, per the brief. */
    #[Session(key: 'clinic.view')]
    public string $view = 'day';

    public ?int $selectedAppointmentId = null;

    public bool $showForm = false;

    public ?int $editingId = null;

    // ---- Booking form ----
    public ?int $clinic_client_id = null;

    public string $clientSearch = '';

    public bool $creatingClient = false;

    public string $newClientName = '';

    public string $newClientOrganization = '';

    public string $newClientPhone = '';

    public string $newClientEmail = '';

    public ?int $clinic_service_id = null;

    public ?int $assigned_staff_id = null;

    public string $appointment_date = '';

    public string $start_time = '09:00';

    public string $end_time = '09:30';

    public string $note = '';

    // ---- Recurrence (only offered when the service allows it) ----
    public bool $isRecurring = false;

    public string $recurrence = 'weekly';

    public array $days_of_week = [];

    public string $series_end_date = '';

    /** Clash summary shown after booking a series. */
    public array $clashSummary = [];

    public function mount(): void
    {
        $this->authorize('viewAny', ClinicAppointment::class);

        $this->date = now()->toDateString();
    }

    // ================= Navigation =================

    public function previousDay(): void
    {
        $this->date = Carbon::parse($this->date)->subDay()->toDateString();
    }

    public function nextDay(): void
    {
        $this->date = Carbon::parse($this->date)->addDay()->toDateString();
    }

    public function previousMonth(): void
    {
        $this->date = Carbon::parse($this->date)->subMonthNoOverflow()->toDateString();
    }

    public function nextMonth(): void
    {
        $this->date = Carbon::parse($this->date)->addMonthNoOverflow()->toDateString();
    }

    public function goToday(): void
    {
        $this->date = now()->toDateString();
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['day', 'month'], true) ? $view : 'day';
    }

    /** Clicking a day card in the month view opens that day. */
    public function openDay(string $date): void
    {
        $this->date = Carbon::parse($date)->toDateString();
        $this->view = 'day';
    }

    // ================= Detail sheet =================

    public function selectAppointment(int $id): void
    {
        $this->selectedAppointmentId = $id;
    }

    public function closeSheet(): void
    {
        $this->selectedAppointmentId = null;
    }

    // ================= Booking form =================

    public function openCreate(?string $date = null): void
    {
        $this->authorize('create', ClinicAppointment::class);

        $this->reset([
            'editingId', 'clinic_client_id', 'clientSearch', 'creatingClient',
            'newClientName', 'newClientOrganization', 'newClientPhone', 'newClientEmail',
            'clinic_service_id', 'assigned_staff_id', 'note',
            'isRecurring', 'days_of_week', 'clashSummary',
        ]);

        $this->recurrence = 'weekly';
        $this->appointment_date = $date ?? $this->date;
        $this->series_end_date = Carbon::parse($this->appointment_date)->addWeeks(4)->toDateString();
        $this->start_time = '09:00';
        $this->end_time = '09:30';
        $this->selectedAppointmentId = null;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $appointment = ClinicAppointment::findOrFail($id);
        $this->authorize('update', $appointment);

        $this->editingId = $appointment->id;
        $this->clinic_client_id = $appointment->clinic_client_id;
        $this->clinic_service_id = $appointment->clinic_service_id;
        $this->assigned_staff_id = $appointment->assigned_staff_id;
        $this->appointment_date = $appointment->starts_at->toDateString();
        $this->start_time = $appointment->starts_at->format('H:i');
        $this->end_time = $appointment->ends_at->format('H:i');
        $this->note = $appointment->note ?? '';

        // Editing acts on the single occurrence; recurrence is set at creation.
        $this->isRecurring = false;
        $this->clashSummary = [];
        $this->selectedAppointmentId = null;
        $this->resetValidation();
        $this->showForm = true;
    }

    /** Picking a service pre-fills the end time from its default duration. */
    public function updatedClinicServiceId(): void
    {
        $service = $this->service();

        if (! $service) {
            return;
        }

        if ($this->start_time) {
            $start = Carbon::createFromFormat('H:i', $this->start_time);
            $this->end_time = $start->copy()->addMinutes($service->default_duration_minutes)->format('H:i');
        }

        // Recurrence is only ever available for services that allow it.
        if (! $service->allows_recurrence) {
            $this->isRecurring = false;
        }
    }

    /** Moving the start keeps the end after it. */
    public function updatedStartTime(): void
    {
        if (! $this->start_time) {
            return;
        }

        if (! $this->end_time || $this->end_time <= $this->start_time) {
            $minutes = $this->service()?->default_duration_minutes ?? 30;
            $end = Carbon::createFromFormat('H:i', $this->start_time)->addMinutes($minutes);

            $this->end_time = $end->format('H:i') > $this->start_time ? $end->format('H:i') : '23:59';
        }
    }

    public function selectClient(int $clientId): void
    {
        $this->clinic_client_id = $clientId;
        $this->creatingClient = false;
        $this->clientSearch = '';
    }

    public function startNewClient(): void
    {
        $this->creatingClient = true;
        $this->clinic_client_id = null;
    }

    public function cancelNewClient(): void
    {
        $this->creatingClient = false;
        $this->reset(['newClientName', 'newClientOrganization', 'newClientPhone', 'newClientEmail']);
    }

    public function save(ClinicBooking $booking): void
    {
        $this->authorize('create', ClinicAppointment::class);

        $rules = [
            'clinic_service_id' => ['required', Rule::exists('clinic_services', 'id')],
            'assigned_staff_id' => ['required', Rule::exists('users', 'id')->where('is_active', true)],
            'appointment_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->creatingClient) {
            $rules['newClientName'] = ['required', 'string', 'max:255'];
            $rules['newClientOrganization'] = ['nullable', 'string', 'max:255'];
            $rules['newClientPhone'] = ['nullable', 'string', 'max:50'];
            $rules['newClientEmail'] = ['nullable', 'email', 'max:255'];
        } else {
            $rules['clinic_client_id'] = ['required', Rule::exists('clinic_clients', 'id')];
        }

        if ($this->isRecurring) {
            $rules['recurrence'] = ['required', Rule::in([Recurrence::Daily->value, Recurrence::Weekly->value])];
            $rules['series_end_date'] = ['required', 'date', 'after_or_equal:appointment_date'];

            if ($this->recurrence === Recurrence::Weekly->value) {
                $rules['days_of_week'] = ['required', 'array', 'min:1'];
            }
        }

        $this->validate($rules);

        // Recurrence must never be possible for a service that forbids it.
        if ($this->isRecurring && ! $this->service()?->allows_recurrence) {
            $this->addError('isRecurring', 'This service cannot be booked as a recurring series.');

            return;
        }

        $clientId = $this->creatingClient
            ? ClinicClient::create([
                'name' => $this->newClientName,
                'organization' => $this->newClientOrganization ?: null,
                'phone' => $this->newClientPhone ?: null,
                'email' => $this->newClientEmail ?: null,
            ])->id
            : $this->clinic_client_id;

        [$start, $end] = $this->window();

        if ($this->editingId) {
            $appointment = ClinicAppointment::findOrFail($this->editingId);
            $this->authorize('update', $appointment);

            // An edit still has to respect the clash rule, ignoring itself.
            if (! app(\App\Services\StaffAvailability::class)->isFree(
                $this->assigned_staff_id, $start, $end, $appointment->id
            )) {
                $this->addError('assigned_staff_id', 'That clinician is not free at the selected time.');

                return;
            }

            $appointment->update([
                'clinic_client_id' => $clientId,
                'clinic_service_id' => $this->clinic_service_id,
                'assigned_staff_id' => $this->assigned_staff_id,
                'starts_at' => $start,
                'ends_at' => $end,
                'note' => $this->note ?: null,
                'has_clash' => false,
            ]);

            session()->flash('status', 'Appointment updated.');
            $this->showForm = false;
            $this->date = $start->toDateString();

            return;
        }

        if ($this->isRecurring) {
            $result = $booking->bookSeries([
                'clinic_client_id' => $clientId,
                'clinic_service_id' => $this->clinic_service_id,
                'assigned_staff_id' => $this->assigned_staff_id,
                'booked_by' => auth()->id(),
                'start_time' => $this->start_time,
                'duration_minutes' => (int) round($start->diffInMinutes($end)),
                'recurrence' => $this->recurrence,
                'days_of_week' => $this->recurrence === Recurrence::Weekly->value
                    ? array_map('intval', $this->days_of_week)
                    : null,
                'start_date' => $this->appointment_date,
                'end_date' => $this->series_end_date,
                'note' => $this->note ?: null,
            ]);

            $total = $result['series']->appointments()->count();
            $clashes = $result['clashes'];

            if ($clashes !== []) {
                // Surfaced at confirmation — the booker may proceed or adjust.
                $this->clashSummary = [
                    'count' => count($clashes),
                    'total' => $total,
                    'clinician' => $result['series']->clinician?->name,
                    'dates' => array_map(fn (Carbon $d) => $d->format('D j M'), $clashes),
                ];

                session()->flash('status', "Series booked: {$total} sessions.");
            } else {
                session()->flash('status', "Series booked: {$total} sessions, no clashes.");
            }

            $this->showForm = false;
            $this->date = Carbon::parse($this->appointment_date)->toDateString();

            return;
        }

        try {
            $booking->bookSingle([
                'clinic_client_id' => $clientId,
                'clinic_service_id' => $this->clinic_service_id,
                'assigned_staff_id' => $this->assigned_staff_id,
                'booked_by' => auth()->id(),
                'starts_at' => $start,
                'ends_at' => $end,
                'note' => $this->note ?: null,
            ]);
        } catch (ClinicClashException $e) {
            $this->addError('assigned_staff_id', $e->getMessage());

            return;
        }

        session()->flash('status', 'Appointment booked.');
        $this->showForm = false;
        $this->date = $start->toDateString();
    }

    // ================= Status lifecycle =================

    public function cancelAppointment(int $id): void
    {
        $appointment = ClinicAppointment::findOrFail($id);
        $this->authorize('cancel', $appointment);

        $appointment->update(['status' => AppointmentStatus::Cancelled]);

        session()->flash('status', 'Appointment cancelled.');
        $this->selectedAppointmentId = null;
    }

    /** Cancels every remaining scheduled occurrence in a series. */
    public function cancelSeries(int $id): void
    {
        $appointment = ClinicAppointment::findOrFail($id);
        $this->authorize('cancel', $appointment);

        if (! $appointment->series_id) {
            $this->cancelAppointment($id);

            return;
        }

        $cancelled = ClinicAppointment::where('series_id', $appointment->series_id)
            ->blocking()
            ->update(['status' => AppointmentStatus::Cancelled]);

        session()->flash('status', "Series cancelled: {$cancelled} sessions.");
        $this->selectedAppointmentId = null;
    }

    public function setStatus(int $id, string $status): void
    {
        $appointment = ClinicAppointment::findOrFail($id);
        $this->authorize('setOutcome', $appointment);

        // Guard the argument directly — it isn't a bound component property.
        abort_unless(in_array($status, AppointmentStatus::values(), true), 422);

        $appointment->update(['status' => AppointmentStatus::from($status)]);

        session()->flash('status', 'Outcome recorded.');
        $this->selectedAppointmentId = null;
    }

    public function dismissClashSummary(): void
    {
        $this->clashSummary = [];
    }

    // ================= Helpers =================

    protected function service(): ?ClinicService
    {
        return $this->clinic_service_id
            ? ClinicService::find($this->clinic_service_id)
            : null;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function window(): array
    {
        $date = Carbon::parse($this->appointment_date);

        return [
            $date->copy()->setTimeFromTimeString($this->start_time),
            $date->copy()->setTimeFromTimeString($this->end_time),
        ];
    }

    /**
     * Lay appointments out on the timeline, splitting overlapping ones into
     * side-by-side columns (the same greedy approach the gym page uses).
     */
    protected function layoutBlocks($appointments): array
    {
        $window = self::DAY_END_MINUTES - self::DAY_START_MINUTES;

        $blocks = $appointments->map(function (ClinicAppointment $appointment) use ($window) {
            $startMin = $appointment->starts_at->hour * 60 + $appointment->starts_at->minute;
            $endMin = $appointment->ends_at->hour * 60 + $appointment->ends_at->minute;

            $start = max($startMin, self::DAY_START_MINUTES);
            $end = min($endMin, self::DAY_END_MINUTES);
            $end = max($end, $start + 20); // keep very short blocks readable

            return [
                'appointment' => $appointment,
                'startMin' => $start,
                'endMin' => $end,
                'top' => ($start - self::DAY_START_MINUTES) / $window * 100,
                'height' => ($end - $start) / $window * 100,
                'column' => 0,
                'columns' => 1,
            ];
        })->sortBy('startMin')->values()->all();

        $clusterStart = 0;
        $clusterEnd = -1;
        $columnEnds = [];

        foreach ($blocks as $i => $block) {
            if ($block['startMin'] >= $clusterEnd && $columnEnds !== []) {
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

    public function render(ClinicBooking $booking)
    {
        $day = Carbon::parse($this->date);

        $dayAppointments = ClinicAppointment::query()
            ->onDay($day)
            ->with(['client', 'service', 'clinician'])
            ->orderBy('starts_at')
            ->get();

        // Month grid, mirroring the events calendar (weeks start Monday).
        $monthDays = [];
        if ($this->view === 'month') {
            $monthStart = $day->copy()->startOfMonth();
            $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
            $gridEnd = $monthStart->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

            $monthAppointments = ClinicAppointment::query()
                ->between($gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay())
                ->with(['client', 'service'])
                ->orderBy('starts_at')
                ->get();

            for ($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay()) {
                $monthDays[] = [
                    'date' => $d->copy(),
                    'inMonth' => $d->month === $day->month,
                    'isToday' => $d->isToday(),
                    'appointments' => $monthAppointments->filter(
                        fn (ClinicAppointment $a) => $a->starts_at->isSameDay($d)
                    )->values(),
                ];
            }
        }

        // Assignment control: who is free for the window currently in the form.
        $clinicianAvailability = [];
        if ($this->showForm && $this->appointment_date && $this->start_time && $this->end_time) {
            try {
                [$start, $end] = $this->window();
                $clinicianAvailability = $booking->clinicianAvailability($start, $end, $this->editingId);
            } catch (\Throwable) {
                $clinicianAvailability = [];
            }
        }

        return view('livewire.clinic.clinic-schedule', [
            'day' => $day,
            'blocks' => $this->layoutBlocks($dayAppointments),
            'dayAppointments' => $dayAppointments,
            'hours' => range(8, 16),
            'monthDays' => $monthDays,
            'monthLabel' => $day->format('F Y'),
            'nextToday' => ClinicAppointment::query()
                ->onDay(now())
                ->blocking()
                ->where('starts_at', '>=', now())
                ->with(['client', 'service', 'clinician'])
                ->orderBy('starts_at')
                ->limit(6)
                ->get(),
            'services' => ClinicService::where('is_active', true)->orderBy('name')->get(),
            'clinicianAvailability' => $clinicianAvailability,
            'clientResults' => $this->clientSearch
                ? ClinicClient::search($this->clientSearch)->orderBy('name')->limit(8)->get()
                : collect(),
            'selectedClient' => $this->clinic_client_id ? ClinicClient::find($this->clinic_client_id) : null,
            'organizations' => ClinicClient::organizations(),
            'selectedAppointment' => $this->selectedAppointmentId
                ? ClinicAppointment::with(['client', 'service', 'clinician', 'booker', 'series'])->find($this->selectedAppointmentId)
                : null,
            'weekdays' => [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'],
        ]);
    }
}
