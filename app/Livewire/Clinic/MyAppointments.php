<?php

namespace App\Livewire\Clinic;

use App\Enums\AppointmentStatus;
use App\Models\ClinicAppointment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A clinician's own appointments and nothing else — no client database, no
 * other clinicians' schedules. The client name and service are shown because
 * they're needed to actually treat the person.
 */
#[Layout('layouts.app')]
class MyAppointments extends Component
{
    use AuthorizesRequests;

    /** Deep-link target from the post-appointment status prompt. */
    #[Url(as: 'appointment')]
    public ?int $highlightId = null;

    public ?int $selectedAppointmentId = null;

    public string $filter = 'upcoming';

    public function mount(): void
    {
        $this->authorize('view-own-appointments');

        // A status-prompt link opens straight onto that appointment, and the
        // "awaiting outcome" list is the useful view in that moment.
        if ($this->highlightId) {
            $appointment = ClinicAppointment::find($this->highlightId);

            if ($appointment && $appointment->assigned_staff_id === auth()->id()) {
                $this->selectedAppointmentId = $appointment->id;
                $this->filter = 'awaiting';
            }
        }
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['upcoming', 'awaiting', 'past'], true) ? $filter : 'upcoming';
    }

    public function selectAppointment(int $id): void
    {
        $this->selectedAppointmentId = $id;
    }

    public function closeSheet(): void
    {
        $this->selectedAppointmentId = null;
    }

    public function setStatus(int $id, string $status): void
    {
        $appointment = $this->ownAppointment($id);
        $this->authorize('setOutcome', $appointment);

        abort_unless(in_array($status, AppointmentStatus::values(), true), 422);

        $appointment->update(['status' => AppointmentStatus::from($status)]);

        session()->flash('status', 'Outcome recorded.');
        $this->selectedAppointmentId = null;
    }

    public function cancelAppointment(int $id): void
    {
        $appointment = $this->ownAppointment($id);
        $this->authorize('cancel', $appointment);

        $appointment->update(['status' => AppointmentStatus::Cancelled]);

        session()->flash('status', 'Appointment cancelled.');
        $this->selectedAppointmentId = null;
    }

    /**
     * Always scoped to the signed-in clinician — a clinician can never act on
     * someone else's appointment, whatever id is submitted.
     */
    protected function ownAppointment(int $id): ClinicAppointment
    {
        return ClinicAppointment::where('assigned_staff_id', auth()->id())
            ->findOrFail($id);
    }

    public function render()
    {
        $base = ClinicAppointment::query()
            ->where('assigned_staff_id', auth()->id())
            ->with(['client', 'service']);

        $appointments = match ($this->filter) {
            // Finished but still marked scheduled — these need an outcome.
            'awaiting' => (clone $base)
                ->blocking()
                ->where('ends_at', '<', now())
                ->orderByDesc('starts_at'),
            'past' => (clone $base)
                ->where('ends_at', '<', now())
                ->orderByDesc('starts_at'),
            default => (clone $base)
                ->where('ends_at', '>=', now())
                ->orderBy('starts_at'),
        };

        return view('livewire.clinic.my-appointments', [
            'appointments' => $appointments->paginate(20),
            'awaitingCount' => (clone $base)->blocking()->where('ends_at', '<', now())->count(),
            'selectedAppointment' => $this->selectedAppointmentId
                ? ClinicAppointment::where('assigned_staff_id', auth()->id())
                    ->with(['client', 'service'])
                    ->find($this->selectedAppointmentId)
                : null,
        ]);
    }
}
