@use('App\Enums\AppointmentStatus')

<div class="py-4 sm:py-6">
    <div class="mx-auto max-w-3xl space-y-4 px-3 sm:px-6 lg:px-8">

        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-bold tracking-tight text-ink sm:text-2xl">My Appointments</h2>
        </div>

        <x-flash />

        {{-- Filters --}}
        <div class="hps-segment">
            <button wire:click="setFilter('upcoming')"
                @class([
                    'flex-1 py-2.5 text-[11px] font-semibold uppercase tracking-label transition',
                    'bg-ink text-ink-100' => $filter === 'upcoming',
                    'bg-white text-ink-800 hover:bg-ink-100' => $filter !== 'upcoming',
                ])>Upcoming</button>

            <button wire:click="setFilter('awaiting')"
                @class([
                    'flex flex-1 items-center justify-center gap-1.5 border-l border-ink-400 py-2.5 text-[11px] font-semibold uppercase tracking-label transition',
                    'bg-ink text-ink-100' => $filter === 'awaiting',
                    'bg-white text-ink-800 hover:bg-ink-100' => $filter !== 'awaiting',
                ])>
                Needs outcome
                @if ($awaitingCount > 0)
                    <span class="bg-accent px-1.5 py-0.5 text-[10px] text-white">{{ $awaitingCount }}</span>
                @endif
            </button>

            <button wire:click="setFilter('past')"
                @class([
                    'flex-1 border-l border-ink-400 py-2.5 text-[11px] font-semibold uppercase tracking-label transition',
                    'bg-ink text-ink-100' => $filter === 'past',
                    'bg-white text-ink-800 hover:bg-ink-100' => $filter !== 'past',
                ])>Past</button>
        </div>

        <div class="hps-panel">
            @forelse ($appointments as $appointment)
                <button wire:click="selectAppointment({{ $appointment->id }})"
                    wire:key="mine-{{ $appointment->id }}"
                    @class([
                        'hps-row flex w-full items-start gap-3 p-4 text-start transition first:border-t-0 hover:bg-ink-100',
                        'bg-accent-100/40' => $highlightId === $appointment->id,
                    ])>
                    <span class="w-14 shrink-0">
                        <span class="block text-[10px] font-semibold uppercase tracking-label text-ink-500">
                            {{ $appointment->starts_at->format('D j M') }}
                        </span>
                        <span class="block text-base font-bold leading-tight text-ink">
                            {{ $appointment->starts_at->format('H:i') }}
                        </span>
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-ink">{{ $appointment->client?->name }}</span>
                        <span class="block truncate text-xs text-ink-700">{{ $appointment->service?->name }}</span>
                        <span class="block text-xs text-ink-500">
                            {{ $appointment->starts_at->format('g:ia') }} – {{ $appointment->ends_at->format('g:ia') }}
                        </span>
                    </span>

                    <span class="shrink-0">
                        <x-status-badge :status="$appointment->status" />
                    </span>
                </button>
            @empty
                <p class="p-10 text-center text-sm text-ink-600">
                    @if ($filter === 'awaiting')
                        Nothing waiting on an outcome.
                    @elseif ($filter === 'past')
                        No past appointments.
                    @else
                        No upcoming appointments.
                    @endif
                </p>
            @endforelse
        </div>

        {{ $appointments->links() }}
    </div>

    {{-- Detail sheet with the status control --}}
    @if ($selectedAppointment)
        <x-sheet :title="$selectedAppointment->client?->name ?? 'Appointment'"
            :badge="$selectedAppointment->service?->name"
            badge-class="bg-clinic text-white"
            close="closeSheet">

            <div class="flex gap-3 border-b border-ink-200 p-3.5">
                <span class="hps-label w-20 shrink-0 pt-0.5">Time</span>
                <span class="text-[13px] font-semibold text-ink">
                    {{ $selectedAppointment->starts_at->format('g:ia') }} – {{ $selectedAppointment->ends_at->format('g:ia') }},
                    {{ $selectedAppointment->starts_at->format('D j M Y') }}
                </span>
            </div>

            <div class="flex gap-3 border-b border-ink-200 p-3.5">
                <span class="hps-label w-20 shrink-0 pt-0.5">Status</span>
                <span><x-status-badge :status="$selectedAppointment->status" /></span>
            </div>

            @if ($selectedAppointment->note)
                <div class="flex flex-col gap-1.5 border-b border-ink-200 p-3.5">
                    <span class="hps-label">Note</span>
                    <p class="whitespace-pre-line text-[13px] leading-relaxed text-ink-800">{{ $selectedAppointment->note }}</p>
                </div>
            @endif

            {{-- Outcome: the clinician's job once the appointment has finished --}}
            @if ($selectedAppointment->status === AppointmentStatus::Scheduled)
                @if ($selectedAppointment->ends_at->isPast())
                    <div class="border-b border-ink-200 bg-ink-100 p-3.5">
                        <span class="hps-label">Record the outcome</span>
                        <div class="mt-2 flex gap-2">
                            <x-dark-button wire:click="setStatus({{ $selectedAppointment->id }}, 'completed')" class="flex-1 justify-center">
                                Completed
                            </x-dark-button>
                            <x-danger-button wire:click="setStatus({{ $selectedAppointment->id }}, 'no_show')" type="button" class="flex-1 justify-center">
                                No show
                            </x-danger-button>
                        </div>
                    </div>
                @else
                    <div class="border-b border-ink-200 p-3.5">
                        <p class="text-xs text-ink-600">
                            You can record the outcome once this appointment has finished.
                        </p>
                    </div>
                @endif

                <x-slot name="actions">
                    <x-danger-button wire:click="cancelAppointment({{ $selectedAppointment->id }})"
                        wire:confirm="Cancel this appointment?" type="button">
                        Cancel appointment
                    </x-danger-button>
                </x-slot>
            @else
                <div class="p-3.5">
                    <p class="text-xs text-ink-600">
                        This appointment is {{ strtolower($selectedAppointment->status->label()) }}.
                    </p>
                </div>
            @endif
        </x-sheet>
    @endif
</div>
