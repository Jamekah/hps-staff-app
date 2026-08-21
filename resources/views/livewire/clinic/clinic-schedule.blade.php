@use('App\Enums\AppointmentStatus')

<div class="py-4 sm:py-6">
    <div class="mx-auto max-w-7xl space-y-4 px-3 sm:px-6 lg:px-8">

        <x-flash />

        {{-- Clash summary after booking a recurring series --}}
        @if ($clashSummary)
            <div class="border-s-4 border-accent bg-accent-100 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-accent-800">
                            {{ $clashSummary['count'] }} of {{ $clashSummary['total'] }} sessions clash for {{ $clashSummary['clinician'] }}
                        </p>
                        <p class="mt-1 text-xs text-accent-800">
                            {{ implode(' · ', $clashSummary['dates']) }}
                        </p>
                        <p class="mt-1.5 text-xs text-ink-700">
                            The series was booked and the clashing sessions are flagged. Adjust them individually if needed.
                        </p>
                    </div>
                    <button wire:click="dismissClashSummary" class="shrink-0 p-1 text-accent-800 transition hover:text-accent" aria-label="Dismiss">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        @endif

        {{-- Header: navigation, view toggle, new booking --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex">
                    <button wire:click="{{ $view === 'month' ? 'previousMonth' : 'previousDay' }}"
                        class="flex h-10 w-10 items-center justify-center border border-ink-400 bg-white text-ink transition hover:bg-ink-100"
                        aria-label="Previous">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button wire:click="{{ $view === 'month' ? 'nextMonth' : 'nextDay' }}"
                        class="flex h-10 w-10 items-center justify-center border border-l-0 border-ink-400 bg-white text-ink transition hover:bg-ink-100"
                        aria-label="Next">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>
                    </button>
                </div>

                <div class="min-w-0">
                    <h2 class="text-lg font-extrabold leading-tight tracking-tight text-ink sm:text-2xl">
                        @if ($view === 'month')
                            {{ $monthLabel }}
                        @else
                            <span class="sm:hidden">{{ $day->format('D j M') }}</span>
                            <span class="hidden sm:inline">{{ $day->format('l j F') }}</span>
                        @endif
                    </h2>
                    @if ($view === 'day')
                        <p class="text-xs text-ink-600">
                            {{ trans_choice(':count appointment|:count appointments', $dayAppointments->count()) }}
                        </p>
                    @endif
                </div>

                <button wire:click="goToday" class="border border-ink-400 bg-white px-3 py-2 text-[11px] font-extrabold uppercase tracking-label text-ink-800 transition hover:bg-ink-100">
                    Today
                </button>
            </div>

            <div class="flex items-center gap-3">
                {{-- Daily / monthly toggle --}}
                <div class="flex border border-ink-400">
                    <button wire:click="setView('day')"
                        @class([
                            'px-3 py-2 text-[11px] font-extrabold uppercase tracking-label transition',
                            'bg-ink text-ink-100' => $view === 'day',
                            'bg-white text-ink-800 hover:bg-ink-100' => $view !== 'day',
                        ])>Day</button>
                    <button wire:click="setView('month')"
                        @class([
                            'border-l border-ink-400 px-3 py-2 text-[11px] font-extrabold uppercase tracking-label transition',
                            'bg-ink text-ink-100' => $view === 'month',
                            'bg-white text-ink-800 hover:bg-ink-100' => $view !== 'month',
                        ])>Month</button>
                </div>

                @can('create', App\Models\ClinicAppointment::class)
                    <x-primary-button wire:click="openCreate" type="button" class="hidden sm:inline-flex">
                        + New Booking
                    </x-primary-button>
                @endcan
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <div class="lg:col-span-3">

                @if ($view === 'day')
                    {{-- ===== Daily timeline, 08:00–16:00 ===== --}}
                    <div class="hps-panel hidden sm:block">
                        <div class="hps-panel-head">
                            <span class="hps-label text-ink">Appointments</span>
                            <span class="text-xs text-ink-600">08:00 – 16:00</span>
                        </div>

                        <div class="flex">
                            <div class="w-16 shrink-0 border-e border-ink-300">
                                @foreach ($hours as $hour)
                                    @if ($hour < 16)
                                        <div class="h-[70px] border-t border-ink-200 pe-2 pt-1 text-end text-[11px] font-semibold text-ink-600">
                                            {{ \Carbon\Carbon::createFromTime($hour)->format('ga') }}
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                            <div class="relative min-w-0 flex-1" style="height: 560px;">
                                @foreach ($hours as $hour)
                                    @if ($hour < 16)
                                        <div class="h-[70px] border-t border-ink-200"></div>
                                    @endif
                                @endforeach

                                @foreach ($blocks as $block)
                                    @php $appointment = $block['appointment']; @endphp
                                    <button wire:click="selectAppointment({{ $appointment->id }})"
                                        wire:key="block-{{ $appointment->id }}"
                                        class="absolute p-px text-start"
                                        style="top: {{ $block['top'] }}%; height: {{ $block['height'] }}%; left: {{ $block['column'] / $block['columns'] * 100 }}%; width: {{ 100 / $block['columns'] }}%;">
                                        <div @class([
                                            'flex h-full flex-col gap-0.5 overflow-hidden border-s-4 p-2 transition',
                                            'border-clinic bg-clinic-tint hover:bg-clinic-tint/60' => ! $appointment->isCancelled(),
                                            'border-ink-400 bg-ink-100 opacity-70' => $appointment->isCancelled(),
                                        ])>
                                            <div class="flex items-baseline justify-between gap-1.5">
                                                <span @class([
                                                    'truncate text-[13px] font-bold',
                                                    'text-clinic-dark' => ! $appointment->isCancelled(),
                                                    'text-ink-600 line-through' => $appointment->isCancelled(),
                                                ])>{{ $appointment->client?->name }}</span>

                                                @if ($appointment->has_clash)
                                                    <span class="shrink-0 bg-accent px-1 py-0.5 text-[9px] font-extrabold uppercase tracking-label text-white" title="Clashes with another commitment">Clash</span>
                                                @endif
                                            </div>

                                            <span @class([
                                                'truncate text-[11px] font-semibold',
                                                'text-clinic-mid' => ! $appointment->isCancelled(),
                                                'text-ink-600' => $appointment->isCancelled(),
                                            ])>{{ $appointment->service?->name }}</span>

                                            <span @class([
                                                'text-[11px]',
                                                'text-clinic-muted' => ! $appointment->isCancelled(),
                                                'text-ink-500' => $appointment->isCancelled(),
                                            ])>
                                                {{ $appointment->starts_at->format('g:ia') }} – {{ $appointment->ends_at->format('g:ia') }}
                                            </span>

                                            <span @class([
                                                'mt-auto truncate text-[10px]',
                                                'text-clinic-muted' => ! $appointment->isCancelled(),
                                                'text-ink-500' => $appointment->isCancelled(),
                                            ])>{{ $appointment->clinician?->name }}</span>
                                        </div>
                                    </button>
                                @endforeach

                                @if (empty($blocks))
                                    <p class="absolute inset-x-0 top-1/2 -translate-y-1/2 text-center text-sm text-ink-600">
                                        No appointments booked for this day.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Phone: chronological list --}}
                    <div class="hps-panel sm:hidden">
                        @forelse ($dayAppointments as $appointment)
                            <button wire:click="selectAppointment({{ $appointment->id }})"
                                wire:key="m-{{ $appointment->id }}"
                                class="hps-row flex w-full gap-2.5 p-3 text-start transition first:border-t-0 hover:bg-ink-100">
                                <span class="w-12 shrink-0 pt-0.5 text-xs font-bold text-ink">
                                    {{ $appointment->starts_at->format('H:i') }}
                                </span>
                                <span @class([
                                    'flex min-w-0 flex-1 flex-col gap-1 border-s-4 p-2.5',
                                    'border-clinic bg-clinic-tint' => ! $appointment->isCancelled(),
                                    'border-ink-400 bg-ink-100 opacity-70' => $appointment->isCancelled(),
                                ])>
                                    <span class="flex items-baseline justify-between gap-2">
                                        <span @class([
                                            'text-sm font-bold',
                                            'text-clinic-dark' => ! $appointment->isCancelled(),
                                            'text-ink-600 line-through' => $appointment->isCancelled(),
                                        ])>{{ $appointment->client?->name }}</span>
                                        @if ($appointment->has_clash)
                                            <span class="shrink-0 bg-accent px-1 py-0.5 text-[9px] font-extrabold uppercase tracking-label text-white">Clash</span>
                                        @endif
                                    </span>
                                    <span class="text-xs font-semibold text-clinic-mid">{{ $appointment->service?->name }}</span>
                                    <span class="text-xs text-clinic-muted">
                                        {{ $appointment->starts_at->format('g:ia') }} – {{ $appointment->ends_at->format('g:ia') }} · {{ $appointment->clinician?->name }}
                                    </span>
                                    <x-status-badge :status="$appointment->status" class="self-start" />
                                </span>
                            </button>
                        @empty
                            <p class="p-6 text-center text-sm text-ink-600">No appointments booked for this day.</p>
                        @endforelse
                    </div>
                @else
                    {{-- ===== Monthly overview ===== --}}
                    <div class="hps-panel">
                        <div class="grid grid-cols-7 border-b border-ink">
                            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)
                                <div class="py-2 text-center text-[10px] font-bold uppercase tracking-label text-ink-700 sm:px-2.5 sm:text-start sm:text-[11px]">
                                    {{ $dow }}
                                </div>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-7">
                            @foreach ($monthDays as $cell)
                                <button wire:key="mday-{{ $cell['date']->toDateString() }}"
                                    wire:click="openDay('{{ $cell['date']->toDateString() }}')"
                                    @class([
                                        'flex min-h-16 flex-col items-start gap-1 border-b border-e border-ink-200 p-1.5 text-start transition hover:bg-clinic-tint sm:min-h-24 sm:p-2',
                                        'bg-white' => $cell['inMonth'],
                                        'bg-ink-100' => ! $cell['inMonth'],
                                    ])>
                                    <span @class([
                                        'flex h-[22px] w-[22px] items-center justify-center text-xs',
                                        'bg-accent font-bold text-white' => $cell['isToday'],
                                        'font-semibold text-ink-800' => ! $cell['isToday'] && $cell['inMonth'],
                                        'font-medium text-ink-400' => ! $cell['isToday'] && ! $cell['inMonth'],
                                    ])>{{ $cell['date']->day }}</span>

                                    @if ($cell['appointments']->isNotEmpty())
                                        @php
                                            $active = $cell['appointments']->filter(fn ($a) => ! $a->isCancelled());
                                        @endphp

                                        <span class="hidden w-full flex-col gap-0.5 sm:flex">
                                            @foreach ($cell['appointments']->take(2) as $a)
                                                <span @class([
                                                    'block w-full truncate px-1 py-0.5 text-[10px] font-semibold',
                                                    'bg-clinic text-white' => ! $a->isCancelled(),
                                                    'bg-ink-200 text-ink-600 line-through' => $a->isCancelled(),
                                                ])>{{ $a->starts_at->format('H:i') }} {{ $a->client?->name }}</span>
                                            @endforeach
                                            @if ($cell['appointments']->count() > 2)
                                                <span class="px-0.5 text-[10px] font-bold text-clinic">
                                                    +{{ $cell['appointments']->count() - 2 }} more
                                                </span>
                                            @endif
                                        </span>

                                        {{-- Phone: a count is all that fits --}}
                                        <span class="flex items-center gap-1 sm:hidden">
                                            <span class="h-1.5 w-1.5 bg-clinic"></span>
                                            <span class="text-[10px] font-bold text-clinic-dark">{{ $active->count() }}</span>
                                        </span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Side panel: today's next appointments --}}
            <div class="hps-panel self-start">
                <div class="hps-panel-head">
                    <span class="hps-label text-ink">Next today</span>
                </div>

                @forelse ($nextToday as $appointment)
                    <button wire:click="selectAppointment({{ $appointment->id }})"
                        wire:key="next-{{ $appointment->id }}"
                        class="hps-row flex w-full items-start gap-3 p-4 text-start transition first:border-t-0 hover:bg-ink-100">
                        <span class="w-12 shrink-0">
                            <span class="block text-sm font-extrabold leading-tight text-ink">{{ $appointment->starts_at->format('H:i') }}</span>
                            <span class="block text-[10px] font-semibold text-ink-500">{{ $appointment->ends_at->format('H:i') }}</span>
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-[13px] font-bold text-ink">{{ $appointment->client?->name }}</span>
                            <span class="block truncate text-xs text-ink-700">{{ $appointment->service?->name }}</span>
                            <span class="block truncate text-xs text-ink-500">{{ $appointment->clinician?->name }}</span>
                        </span>
                    </button>
                @empty
                    <p class="p-4 text-sm text-ink-600">Nothing further today.</p>
                @endforelse
            </div>
        </div>

        @can('create', App\Models\ClinicAppointment::class)
            <x-primary-button wire:click="openCreate" type="button" class="w-full justify-start py-4 sm:hidden">
                + New Booking
            </x-primary-button>
        @endcan
    </div>

    {{-- ===== Appointment detail sheet ===== --}}
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
                <span class="hps-label w-20 shrink-0 pt-0.5">Clinician</span>
                <span class="text-[13px] font-semibold text-ink">{{ $selectedAppointment->clinician?->name }}</span>
            </div>

            <div class="flex gap-3 border-b border-ink-200 p-3.5">
                <span class="hps-label w-20 shrink-0 pt-0.5">Status</span>
                <span><x-status-badge :status="$selectedAppointment->status" /></span>
            </div>

            @if ($selectedAppointment->client?->organization)
                <div class="flex gap-3 border-b border-ink-200 p-3.5">
                    <span class="hps-label w-20 shrink-0 pt-0.5">Org</span>
                    <span class="text-[13px] font-semibold text-ink">{{ $selectedAppointment->client->organization }}</span>
                </div>
            @endif

            @if ($selectedAppointment->series_id)
                <div class="flex gap-3 border-b border-ink-200 p-3.5">
                    <span class="hps-label w-20 shrink-0 pt-0.5">Series</span>
                    <span class="text-[13px] font-semibold text-ink">
                        Part of a recurring series
                        <span class="block font-normal text-ink-600">
                            {{ $selectedAppointment->series?->appointments()->count() }} sessions
                        </span>
                    </span>
                </div>
            @endif

            @if ($selectedAppointment->has_clash)
                <div class="border-b border-ink-200 bg-accent-100 p-3.5">
                    <p class="text-xs font-bold text-accent-800">This session clashes with another commitment for the clinician.</p>
                </div>
            @endif

            @if ($selectedAppointment->note)
                <div class="flex flex-col gap-1.5 border-b border-ink-200 p-3.5">
                    <span class="hps-label">Note</span>
                    <p class="whitespace-pre-line text-[13px] leading-relaxed text-ink-800">{{ $selectedAppointment->note }}</p>
                </div>
            @endif

            <div class="flex flex-col gap-2 p-3.5">
                <span class="hps-label">Booked by</span>
                <span class="text-[13px] text-ink-700">
                    {{ $selectedAppointment->booker?->name }} · {{ $selectedAppointment->created_at->format('j M Y') }}
                </span>
            </div>

            {{-- Outcome controls, for appointments that have finished --}}
            @can('setOutcome', $selectedAppointment)
                @if ($selectedAppointment->status === AppointmentStatus::Scheduled && $selectedAppointment->ends_at->isPast())
                    <div class="border-t border-ink-200 bg-ink-100 p-3.5">
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
                @endif
            @endcan

            @can('update', $selectedAppointment)
                <x-slot name="actions">
                    @if ($selectedAppointment->status === AppointmentStatus::Scheduled)
                        <x-dark-button wire:click="openEdit({{ $selectedAppointment->id }})" class="flex-1 justify-start">
                            Edit
                        </x-dark-button>

                        <x-danger-button wire:click="cancelAppointment({{ $selectedAppointment->id }})"
                            wire:confirm="Cancel this appointment?" type="button">
                            Cancel
                        </x-danger-button>

                        @if ($selectedAppointment->series_id)
                            <x-danger-button wire:click="cancelSeries({{ $selectedAppointment->id }})"
                                wire:confirm="Cancel every remaining session in this series?" type="button">
                                Cancel series
                            </x-danger-button>
                        @endif
                    @else
                        <p class="py-2 text-xs text-ink-600">
                            This appointment is {{ strtolower($selectedAppointment->status->label()) }} and is kept as history.
                        </p>
                    @endif
                </x-slot>
            @endcan
        </x-sheet>
    @endif

    {{-- ===== Booking form ===== --}}
    @if ($showForm)
        <x-sheet :title="$editingId ? 'Edit Appointment' : 'New Booking'" close="$set('showForm', false)" wide>
            <form wire:submit="save" class="space-y-4 p-4">

                {{-- Client: pick an existing one or create inline --}}
                <div>
                    <x-input-label value="Client" />

                    @if ($creatingClient)
                        <div class="mt-1 space-y-3 border border-ink-400 bg-surface p-3">
                            <div class="flex items-center justify-between">
                                <span class="hps-label">New client</span>
                                <button type="button" wire:click="cancelNewClient"
                                    class="text-[11px] font-bold uppercase tracking-label text-accent-700 transition hover:text-accent">
                                    Pick existing
                                </button>
                            </div>

                            <div>
                                <x-input-label for="nc-name" value="Name" />
                                <x-text-input wire:model="newClientName" id="nc-name" type="text" class="mt-1" />
                                <x-input-error :messages="$errors->get('newClientName')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label for="nc-org" value="Organization" />
                                <x-text-input wire:model="newClientOrganization" id="nc-org" type="text" class="mt-1" list="clinic-organizations" />
                                <datalist id="clinic-organizations">
                                    @foreach ($organizations as $org)
                                        <option value="{{ $org }}"></option>
                                    @endforeach
                                </datalist>
                                <x-input-error :messages="$errors->get('newClientOrganization')" class="mt-1" />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <x-input-label for="nc-phone" value="Phone" />
                                    <x-text-input wire:model="newClientPhone" id="nc-phone" type="text" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="nc-email" value="Email" />
                                    <x-text-input wire:model="newClientEmail" id="nc-email" type="email" class="mt-1" />
                                    <x-input-error :messages="$errors->get('newClientEmail')" class="mt-1" />
                                </div>
                            </div>
                        </div>
                    @elseif ($selectedClient)
                        <div class="mt-1 flex items-center justify-between border border-ink-400 bg-surface p-3">
                            <div>
                                <p class="text-sm font-bold text-ink">{{ $selectedClient->name }}</p>
                                @if ($selectedClient->organization)
                                    <p class="text-xs text-ink-600">{{ $selectedClient->organization }}</p>
                                @endif
                            </div>
                            <button type="button" wire:click="$set('clinic_client_id', null)"
                                class="text-[11px] font-bold uppercase tracking-label text-ink-700 transition hover:text-ink">
                                Change
                            </button>
                        </div>
                    @else
                        <div class="mt-1 space-y-2">
                            <input type="text" wire:model.live.debounce.300ms="clientSearch"
                                placeholder="Search clients by name…"
                                class="w-full border border-ink-400 bg-surface px-2.5 py-1.5 text-sm text-ink placeholder:text-ink-500 focus:border-accent focus:ring-0">

                            @if ($clientResults->isNotEmpty())
                                <div class="max-h-40 overflow-y-auto border border-ink-400">
                                    @foreach ($clientResults as $client)
                                        <button type="button" wire:click="selectClient({{ $client->id }})"
                                            wire:key="cr-{{ $client->id }}"
                                            class="block w-full border-b border-ink-200 p-2.5 text-start text-sm transition last:border-b-0 hover:bg-ink-100">
                                            <span class="font-bold text-ink">{{ $client->name }}</span>
                                            @if ($client->organization)
                                                <span class="block text-xs text-ink-600">{{ $client->organization }}</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            @elseif ($clientSearch)
                                <p class="text-xs text-ink-600">No match.</p>
                            @endif

                            <button type="button" wire:click="startNewClient"
                                class="text-[11px] font-bold uppercase tracking-label text-accent-700 transition hover:text-accent">
                                + New client
                            </button>
                        </div>
                    @endif

                    <x-input-error :messages="$errors->get('clinic_client_id')" class="mt-1" />
                </div>

                {{-- Service --}}
                <div>
                    <x-input-label for="service" value="Service" />
                    <select wire:model.live="clinic_service_id" id="service"
                        class="mt-1 block w-full border border-ink-400 bg-surface px-2.5 py-1.5 text-sm text-ink focus:border-accent focus:ring-0">
                        <option value="">Choose a service…</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}">
                                {{ $service->name }} ({{ $service->default_duration_minutes }} min)
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('clinic_service_id')" class="mt-1" />
                </div>

                {{-- Date and times --}}
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <x-input-label for="appt-date" value="Date" />
                        <x-text-input wire:model.live="appointment_date" id="appt-date" type="date" class="mt-1" />
                        <x-input-error :messages="$errors->get('appointment_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="appt-start" value="Start" />
                        <x-text-input wire:model.live="start_time" id="appt-start" type="time" class="mt-1" />
                        <x-input-error :messages="$errors->get('start_time')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="appt-end" value="End" />
                        <x-text-input wire:model.live="end_time" id="appt-end" type="time" class="mt-1" />
                        <x-input-error :messages="$errors->get('end_time')" class="mt-1" />
                    </div>
                </div>

                {{-- Clinician: clashing ones are greyed out and unselectable --}}
                <div>
                    <x-input-label value="Assign clinician" />

                    @if (empty($clinicianAvailability))
                        <p class="mt-1 border border-ink-400 bg-surface p-3 text-xs text-ink-600">
                            No clinicians available. Set a date and time, and make sure at least one active user is
                            flagged as a clinician on the Users page.
                        </p>
                    @else
                        <div class="mt-1 max-h-44 space-y-1 overflow-y-auto border border-ink-400 bg-surface p-2.5">
                            @foreach ($clinicianAvailability as $userId => $entry)
                                <label wire:key="clin-{{ $userId }}"
                                    @class([
                                        'flex items-center gap-2.5 p-1.5 text-sm',
                                        'cursor-pointer text-ink-800 hover:bg-ink-100' => $entry['free'],
                                        'cursor-not-allowed text-ink-400' => ! $entry['free'],
                                    ])>
                                    <input type="radio" wire:model="assigned_staff_id" value="{{ $userId }}"
                                        @disabled(! $entry['free'])
                                        class="border-ink-400 text-clinic focus:ring-clinic disabled:opacity-40">
                                    <span class="flex-1">{{ $entry['user']->name }}</span>
                                    @unless ($entry['free'])
                                        <span class="text-[10px] font-bold uppercase tracking-label text-accent-700">Busy</span>
                                    @endunless
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-ink-600">
                            Clinicians already committed to an event, gym session or appointment at this time cannot be selected.
                        </p>
                    @endif

                    <x-input-error :messages="$errors->get('assigned_staff_id')" class="mt-1" />
                </div>

                {{-- Recurrence: only offered when the service allows it --}}
                @php
                    $selectedService = $services->firstWhere('id', $clinic_service_id);
                @endphp

                @if ($selectedService?->allows_recurrence && ! $editingId)
                    <div class="border border-ink-400 bg-surface p-3">
                        <label class="flex cursor-pointer items-center gap-2.5 text-sm font-bold text-ink">
                            <input type="checkbox" wire:model.live="isRecurring" class="border-ink-400 text-clinic focus:ring-clinic">
                            Repeat this appointment
                        </label>

                        @if ($isRecurring)
                            <div class="mt-3 space-y-3">
                                <div>
                                    <x-input-label for="recurrence" value="Repeats" />
                                    <select wire:model.live="recurrence" id="recurrence"
                                        class="mt-1 block w-full border border-ink-400 bg-white px-2.5 py-1.5 text-sm text-ink focus:border-accent focus:ring-0">
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly (selected weekdays)</option>
                                    </select>
                                </div>

                                @if ($recurrence === 'weekly')
                                    <div>
                                        <x-input-label value="On weekdays" />
                                        <div class="mt-1 flex flex-wrap gap-1.5">
                                            @foreach ($weekdays as $value => $label)
                                                <label class="flex cursor-pointer items-center gap-1.5 border border-ink-400 bg-white px-2.5 py-1.5 text-xs font-semibold transition has-[:checked]:border-clinic has-[:checked]:bg-clinic has-[:checked]:text-white">
                                                    <input type="checkbox" wire:model="days_of_week" value="{{ $value }}" class="sr-only">
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                        <x-input-error :messages="$errors->get('days_of_week')" class="mt-1" />
                                    </div>
                                @endif

                                <div>
                                    <x-input-label for="series-end" value="Until" />
                                    <x-text-input wire:model="series_end_date" id="series-end" type="date" class="mt-1" />
                                    <x-input-error :messages="$errors->get('series_end_date')" class="mt-1" />
                                </div>

                                <p class="text-xs text-ink-700">
                                    Sessions that clash with the clinician's other commitments are flagged, not blocked —
                                    you'll get a summary after booking.
                                </p>
                            </div>
                        @endif
                    </div>
                @endif

                <div>
                    <x-input-label for="appt-note" value="Note" />
                    <textarea wire:model="note" id="appt-note" rows="2"
                        class="mt-1 block w-full border border-ink-400 bg-surface px-2.5 py-1.5 text-sm text-ink caret-accent focus:border-accent focus:ring-0"></textarea>
                    <x-input-error :messages="$errors->get('note')" class="mt-1" />
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <x-secondary-button wire:click="$set('showForm', false)" type="button">Cancel</x-secondary-button>
                    <x-primary-button>{{ $editingId ? 'Save Changes' : 'Book Appointment' }}</x-primary-button>
                </div>
            </form>
        </x-sheet>
    @endif
</div>
