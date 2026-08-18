<div class="py-4 sm:py-6">
    <div class="mx-auto max-w-7xl space-y-4 px-3 sm:px-6 lg:px-8">

        <x-flash />

        {{-- Day navigation, legend, and the admin create action --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex">
                    <button wire:click="previousDay" class="flex h-10 w-10 items-center justify-center border border-ink-400 bg-white text-ink transition hover:bg-ink-100" aria-label="Previous day">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button wire:click="nextDay" class="flex h-10 w-10 items-center justify-center border border-l-0 border-ink-400 bg-white text-ink transition hover:bg-ink-100" aria-label="Next day">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>
                    </button>
                </div>

                <div class="min-w-0">
                    <h2 class="text-lg font-extrabold leading-tight tracking-tight text-ink sm:text-2xl">
                        <span class="sm:hidden">{{ $day->format('D j M') }}</span>
                        <span class="hidden sm:inline">{{ $day->format('l j F') }}</span>
                    </h2>
                    <p class="text-xs text-ink-600 sm:hidden">
                        {{ trans_choice(':count session|:count sessions', count($blocks)) }}
                    </p>
                </div>

                <input type="date" wire:model.live="date"
                    class="hidden h-10 border border-ink-400 bg-white px-2.5 text-xs font-semibold text-ink-800 focus:border-accent focus:ring-0 sm:block">

                <button wire:click="goToday" class="border border-ink-400 bg-white px-3 py-2 text-[11px] font-extrabold uppercase tracking-label text-ink-800 transition hover:bg-ink-100">
                    Today
                </button>
            </div>

            <div class="flex items-center gap-5">
                <div class="hidden items-center gap-4 text-xs font-semibold text-ink-800 sm:flex">
                    <span class="flex items-center gap-2"><span class="h-3 w-3 bg-studio1"></span> Studio 1</span>
                    <span class="flex items-center gap-2"><span class="h-3 w-3 bg-studio2"></span> Studio 2</span>
                </div>

                @can('create', App\Models\GymSchedule::class)
                    <x-primary-button wire:click="openCreate" type="button" class="hidden sm:inline-flex">
                        + New Session
                    </x-primary-button>
                @endcan
            </div>
        </div>

        {{-- Phone: studio filter --}}
        <div class="flex border border-ink-400 sm:hidden">
            <button wire:click="$set('studioFilter', 'all')"
                @class([
                    'flex-1 py-2.5 text-xs font-bold uppercase tracking-label transition',
                    'bg-ink text-ink-100' => $studioFilter === 'all',
                    'bg-white text-ink-800' => $studioFilter !== 'all',
                ])>All</button>
            <button wire:click="$set('studioFilter', '1')"
                @class([
                    'flex flex-1 items-center justify-center gap-1.5 border-l border-ink-400 py-2.5 text-xs font-bold uppercase tracking-label transition',
                    'bg-ink text-ink-100' => $studioFilter === '1',
                    'bg-white text-ink-800' => $studioFilter !== '1',
                ])><span class="h-2 w-2 bg-studio1"></span> Studio 1</button>
            <button wire:click="$set('studioFilter', '2')"
                @class([
                    'flex flex-1 items-center justify-center gap-1.5 border-l border-ink-400 py-2.5 text-xs font-bold uppercase tracking-label transition',
                    'bg-ink text-ink-100' => $studioFilter === '2',
                    'bg-white text-ink-800' => $studioFilter !== '2',
                ])><span class="h-2 w-2 bg-studio2"></span> Studio 2</button>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <div class="lg:col-span-3">

                {{-- Desktop: positioned day timeline --}}
                <div class="hps-panel hidden sm:block">
                    <div class="hps-panel-head">
                        <span class="hps-label text-ink">Sessions</span>
                        <span class="text-xs text-ink-600">
                            {{ trans_choice(':count session|:count sessions', count($blocks)) }} · 07:00 – 19:00
                        </span>
                    </div>

                    <div class="flex">
                        {{-- Hour gutter --}}
                        <div class="w-16 shrink-0 border-e border-ink-300">
                            @foreach ($hours as $hour)
                                @if ($hour < 19)
                                    <div class="h-[62px] border-t border-ink-200 pe-2 pt-1 text-end text-[11px] font-semibold text-ink-600">
                                        {{ \Carbon\Carbon::createFromTime($hour)->format('ga') }}
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        {{-- Blocks --}}
                        <div class="relative min-w-0 flex-1" style="height: 744px;">
                            @foreach ($hours as $hour)
                                @if ($hour < 19)
                                    <div class="h-[62px] border-t border-ink-200"></div>
                                @endif
                            @endforeach

                            @foreach ($blocks as $block)
                                @php $session = $block['session']; @endphp
                                <button wire:click="selectSession({{ $session->id }})"
                                    wire:key="block-{{ $session->id }}"
                                    class="absolute p-px text-start"
                                    style="top: {{ $block['top'] }}%; height: {{ $block['height'] }}%; left: {{ $block['column'] / $block['columns'] * 100 }}%; width: {{ 100 / $block['columns'] }}%;">
                                    <div @class([
                                        'flex h-full flex-col gap-0.5 overflow-hidden border-s-4 p-2 transition',
                                        'border-studio1 bg-studio1-tint hover:bg-studio1-tint/60' => (string) $session->studio === '1',
                                        'border-studio2 bg-studio2-tint hover:bg-studio2-tint/60' => (string) $session->studio === '2',
                                    ])>
                                        <div class="flex items-baseline justify-between gap-1.5">
                                            <span @class([
                                                'truncate text-[13px] font-bold',
                                                'text-studio1-dark' => (string) $session->studio === '1',
                                                'text-studio2-dark' => (string) $session->studio === '2',
                                            ])>{{ $session->name }}</span>
                                            <span @class([
                                                'shrink-0 px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-label text-white',
                                                'bg-studio1' => (string) $session->studio === '1',
                                                'bg-studio2' => (string) $session->studio === '2',
                                            ])>S{{ $session->studio }}</span>
                                        </div>
                                        <span @class([
                                            'text-[11px] font-semibold',
                                            'text-studio1-mid' => (string) $session->studio === '1',
                                            'text-studio2-mid' => (string) $session->studio === '2',
                                        ])>
                                            {{ \Carbon\Carbon::parse($session->start_time)->format('g:ia') }} – {{ \Carbon\Carbon::parse($session->end_time)->format('g:ia') }}
                                        </span>
                                        <span @class([
                                            'truncate text-[11px]',
                                            'text-studio1-muted' => (string) $session->studio === '1',
                                            'text-studio2-muted' => (string) $session->studio === '2',
                                        ])>{{ $session->client_name }}</span>
                                        @if ($session->staff->isNotEmpty())
                                            <span @class([
                                                'mt-auto truncate text-[10px]',
                                                'text-studio1-muted' => (string) $session->studio === '1',
                                                'text-studio2-muted' => (string) $session->studio === '2',
                                            ])>{{ $session->staff->pluck('name')->join(', ') }}</span>
                                        @endif
                                    </div>
                                </button>
                            @endforeach

                            @if ($nowOffset !== null)
                                <div class="pointer-events-none absolute inset-x-0 flex items-center" style="top: {{ $nowOffset }}%;">
                                    <span class="h-[7px] w-[7px] shrink-0 bg-accent"></span>
                                    <span class="h-0.5 flex-1 bg-accent"></span>
                                    <span class="shrink-0 bg-accent px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-label text-white">Now</span>
                                </div>
                            @endif

                            @if (empty($blocks))
                                <p class="absolute inset-x-0 top-1/2 -translate-y-1/2 text-center text-sm text-ink-600">
                                    No sessions scheduled for this day.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Phone: chronological list under a time gutter --}}
                <div class="hps-panel sm:hidden">
                    @forelse ($mobileRows as $row)
                        <div wire:key="row-{{ $row['start'] }}" class="hps-row flex gap-2.5 p-3 first:border-t-0">
                            <div class="w-12 shrink-0 pt-0.5 text-xs font-bold text-ink">{{ $row['start'] }}</div>
                            <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                                @foreach ($row['sessions'] as $session)
                                    <button wire:click="selectSession({{ $session->id }})"
                                        wire:key="msession-{{ $session->id }}"
                                        @class([
                                            'flex flex-col gap-1 border-s-4 p-2.5 text-start',
                                            'border-studio1 bg-studio1-tint' => (string) $session->studio === '1',
                                            'border-studio2 bg-studio2-tint' => (string) $session->studio === '2',
                                        ])>
                                        <span class="flex items-baseline justify-between gap-2">
                                            <span @class([
                                                'text-sm font-bold',
                                                'text-studio1-dark' => (string) $session->studio === '1',
                                                'text-studio2-dark' => (string) $session->studio === '2',
                                            ])>{{ $session->name }}</span>
                                            <span @class([
                                                'shrink-0 px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-label text-white',
                                                'bg-studio1' => (string) $session->studio === '1',
                                                'bg-studio2' => (string) $session->studio === '2',
                                            ])>S{{ $session->studio }}</span>
                                        </span>
                                        <span @class([
                                            'text-xs font-semibold',
                                            'text-studio1-mid' => (string) $session->studio === '1',
                                            'text-studio2-mid' => (string) $session->studio === '2',
                                        ])>
                                            {{ \Carbon\Carbon::parse($session->start_time)->format('g:ia') }} – {{ \Carbon\Carbon::parse($session->end_time)->format('g:ia') }}
                                        </span>
                                        <span @class([
                                            'text-xs',
                                            'text-studio1-muted' => (string) $session->studio === '1',
                                            'text-studio2-muted' => (string) $session->studio === '2',
                                        ])>{{ $session->client_name }}</span>
                                        @if ($session->staff->isNotEmpty())
                                            <span @class([
                                                'text-[11px]',
                                                'text-studio1-muted' => (string) $session->studio === '1',
                                                'text-studio2-muted' => (string) $session->studio === '2',
                                            ])>{{ $session->staff->pluck('name')->join(', ') }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="p-6 text-center text-sm text-ink-600">No sessions scheduled for this day.</p>
                    @endforelse
                </div>
            </div>

            {{-- Day at a glance --}}
            <div class="hps-panel hidden self-start lg:block">
                <div class="hps-panel-head">
                    <span class="hps-label text-ink">Day at a glance</span>
                </div>

                <div class="flex border-b border-ink-200">
                    <div class="flex-1 border-e border-ink-200 p-4">
                        <div class="text-[11px] font-bold uppercase tracking-label text-studio1">Studio 1</div>
                        <div class="text-2xl font-extrabold leading-tight text-ink">{{ $studio1Count }}</div>
                        <div class="text-xs text-ink-600">{{ Str::plural('session', $studio1Count) }}</div>
                    </div>
                    <div class="flex-1 p-4">
                        <div class="text-[11px] font-bold uppercase tracking-label text-studio2">Studio 2</div>
                        <div class="text-2xl font-extrabold leading-tight text-ink">{{ $studio2Count }}</div>
                        <div class="text-xs text-ink-600">{{ Str::plural('session', $studio2Count) }}</div>
                    </div>
                </div>

                <div class="p-4 text-xs text-ink-600">
                    Overlapping sessions are shown side by side — both studios can run at once.
                </div>
            </div>
        </div>

        {{-- Phone: full-width create action --}}
        @can('create', App\Models\GymSchedule::class)
            <x-primary-button wire:click="openCreate" type="button" class="w-full justify-start py-4 sm:hidden">
                + New Session
            </x-primary-button>
        @endcan
    </div>

    {{-- Session detail sheet --}}
    @if ($selectedSchedule)
        <x-sheet :title="$selectedSchedule->name"
            :badge="'Studio ' . $selectedSchedule->studio"
            :badge-class="(string) $selectedSchedule->studio === '1' ? 'bg-studio1 text-white' : 'bg-studio2 text-white'"
            close="closeSheet">

            <div class="flex gap-3 border-b border-ink-200 p-3.5">
                <span class="hps-label w-20 shrink-0 pt-0.5">Time</span>
                <span class="text-[13px] font-semibold text-ink">
                    {{ \Carbon\Carbon::parse($selectedSchedule->start_time)->format('g:ia') }} – {{ \Carbon\Carbon::parse($selectedSchedule->end_time)->format('g:ia') }}, {{ $day->format('D j M') }}
                </span>
            </div>

            <div class="flex gap-3 border-b border-ink-200 p-3.5">
                <span class="hps-label w-20 shrink-0 pt-0.5">Client</span>
                <span class="text-[13px] font-semibold text-ink">
                    {{ $selectedSchedule->client_name }}
                    <span class="font-normal text-ink-600">— {{ $selectedSchedule->client_type->label() }}</span>
                </span>
            </div>

            <div class="flex gap-3 border-b border-ink-200 p-3.5">
                <span class="hps-label w-20 shrink-0 pt-0.5">Recurs</span>
                <span class="text-[13px] font-semibold text-ink">
                    {{ $selectedSchedule->recurrence->label() }}
                    @if ($selectedSchedule->recurrence === App\Enums\Recurrence::Weekly && $selectedSchedule->days_of_week)
                        · {{ collect($selectedSchedule->days_of_week)->map(fn ($d) => $weekdays[$d] ?? '')->filter()->join(', ') }}
                    @endif
                    <span class="block font-normal text-ink-600">
                        {{ $selectedSchedule->start_date->format('j M Y') }} – {{ $selectedSchedule->end_date->format('j M Y') }}
                    </span>
                </span>
            </div>

            <div class="flex flex-col gap-2 p-3.5">
                <span class="hps-label">Allocated staff</span>
                @if ($selectedSchedule->staff->isEmpty())
                    <span class="text-[13px] text-ink-600">No staff allocated.</span>
                @else
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($selectedSchedule->staff as $member)
                            <span class="hps-chip">{{ $member->name }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            @can('update', $selectedSchedule)
                <x-slot name="actions">
                    <x-dark-button wire:click="openEdit({{ $selectedSchedule->id }})" class="flex-1 justify-start">
                        Edit Session
                    </x-dark-button>
                    <x-danger-button wire:click="delete({{ $selectedSchedule->id }})"
                        wire:confirm="Delete this session and its whole series? This cannot be undone." type="button">
                        Delete
                    </x-danger-button>
                </x-slot>
            @endcan
        </x-sheet>
    @endif

    {{-- Create / edit form (acts on the whole series) --}}
    @if ($showForm)
        <x-sheet :title="$editingId ? 'Edit Session (whole series)' : 'New Session'" close="$set('showForm', false)" wide>
            <form wire:submit="save" class="space-y-4 p-4">
                <div>
                    <x-input-label for="gs-name" value="Session name" />
                    <x-text-input wire:model="name" id="gs-name" type="text" class="mt-1" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <x-input-label for="client_type" value="Client type" />
                        <select wire:model="client_type" id="client_type"
                            class="mt-1 block w-full border border-ink-400 bg-surface px-2.5 py-1.5 text-sm text-ink focus:border-accent focus:ring-0">
                            <option value="national_federation">National Federation</option>
                            <option value="external_client">External Client</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="client_name" value="Client name" />
                        <x-text-input wire:model="client_name" id="client_name" type="text" class="mt-1" />
                        <x-input-error :messages="$errors->get('client_name')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label value="Studio" />
                    <div class="mt-1 flex">
                        <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 border border-ink-400 py-2.5 text-[13px] font-semibold transition has-[:checked]:bg-studio1 has-[:checked]:text-white">
                            <input type="radio" wire:model="studio" value="1" class="sr-only">
                            <span class="h-2.5 w-2.5 bg-studio1"></span> Studio 1
                        </label>
                        <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 border border-l-0 border-ink-400 py-2.5 text-[13px] font-semibold transition has-[:checked]:bg-studio2 has-[:checked]:text-white">
                            <input type="radio" wire:model="studio" value="2" class="sr-only">
                            <span class="h-2.5 w-2.5 bg-studio2"></span> Studio 2
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('studio')" class="mt-1" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="gs-start_date" value="Start date" />
                        <x-text-input wire:model.live="start_date" id="gs-start_date" type="date" class="mt-1" />
                        <x-input-error :messages="$errors->get('start_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="gs-end_date" value="End date" />
                        <x-text-input wire:model="end_date" id="gs-end_date" type="date" class="mt-1" />
                        <x-input-error :messages="$errors->get('end_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="gs-start_time" value="Start time" />
                        <x-text-input wire:model.live="start_time" id="gs-start_time" type="time" class="mt-1" />
                        <x-input-error :messages="$errors->get('start_time')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="gs-end_time" value="Finish time" />
                        <x-text-input wire:model="end_time" id="gs-end_time" type="time" class="mt-1" />
                        <x-input-error :messages="$errors->get('end_time')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="recurrence" value="Repeats" />
                    <select wire:model.live="recurrence" id="recurrence"
                        class="mt-1 block w-full border border-ink-400 bg-surface px-2.5 py-1.5 text-sm text-ink focus:border-accent focus:ring-0">
                        <option value="none">Does not repeat</option>
                        <option value="daily">Daily (every day between the dates)</option>
                        <option value="weekly">Weekly (selected weekdays)</option>
                    </select>
                    <x-input-error :messages="$errors->get('recurrence')" class="mt-1" />
                </div>

                @if ($recurrence === 'weekly')
                    <div>
                        <x-input-label value="On weekdays" />
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            @foreach ($weekdays as $value => $label)
                                <label class="flex cursor-pointer items-center gap-1.5 border border-ink-400 px-2.5 py-1.5 text-xs font-semibold transition has-[:checked]:border-accent has-[:checked]:bg-accent has-[:checked]:text-white">
                                    <input type="checkbox" wire:model="days_of_week" value="{{ $value }}" class="sr-only">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('days_of_week')" class="mt-1" />
                    </div>
                @endif

                <div>
                    <x-input-label value="Allocate staff" />
                    <div class="mt-1 max-h-40 space-y-1 overflow-y-auto border border-ink-400 bg-surface p-2.5">
                        @foreach ($activeStaff as $member)
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-800">
                                <input type="checkbox" wire:model="staffIds" value="{{ $member->id }}"
                                    class="border-ink-400 text-accent focus:ring-accent">
                                {{ $member->name }}
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('staffIds')" class="mt-1" />
                    <x-input-error :messages="collect($errors->get('staffIds.*'))->flatten()->all()" class="mt-1" />
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <x-secondary-button wire:click="$set('showForm', false)" type="button">Cancel</x-secondary-button>
                    <x-primary-button>{{ $editingId ? 'Save Changes' : 'Create Session' }}</x-primary-button>
                </div>
            </form>
        </x-sheet>
    @endif
</div>
