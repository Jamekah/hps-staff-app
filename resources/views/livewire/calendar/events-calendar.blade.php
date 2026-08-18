<div class="py-4 sm:py-6">
    <div class="mx-auto max-w-7xl space-y-4 px-3 sm:px-6 lg:px-8">

        <x-flash />

        {{-- Month navigation, legend, and the admin create action --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex">
                    <button wire:click="previousMonth" class="flex h-10 w-10 items-center justify-center border border-ink-400 bg-white text-ink transition hover:bg-ink-100" aria-label="Previous month">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button wire:click="nextMonth" class="flex h-10 w-10 items-center justify-center border border-l-0 border-ink-400 bg-white text-ink transition hover:bg-ink-100" aria-label="Next month">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>
                    </button>
                </div>

                <h2 class="text-lg font-extrabold tracking-tight text-ink sm:text-2xl">{{ $monthLabel }}</h2>

                <button wire:click="goToday" class="border border-ink-400 bg-white px-3 py-2 text-[11px] font-extrabold uppercase tracking-label text-ink-800 transition hover:bg-ink-100">
                    Today
                </button>
            </div>

            <div class="flex items-center gap-5">
                <div class="flex items-center gap-4 text-xs font-semibold text-ink-800">
                    <span class="flex items-center gap-2"><span class="h-3 w-3 bg-ink-900"></span> Internal</span>
                    <span class="flex items-center gap-2"><span class="h-3 w-3 bg-accent"></span> External</span>
                </div>

                @can('create', App\Models\Event::class)
                    <x-primary-button wire:click="openCreate" type="button" class="hidden sm:inline-flex">
                        + New Event
                    </x-primary-button>
                @endcan
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <div class="space-y-4 lg:col-span-3">
                {{-- Month grid --}}
                <div class="hps-panel">
                    <div class="grid grid-cols-7 border-b border-ink">
                        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)
                            <div class="py-2 text-center text-[10px] font-bold uppercase tracking-label text-ink-700 sm:px-2.5 sm:text-start sm:text-[11px]">
                                {{ $dow }}
                            </div>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-7">
                        @foreach ($days as $day)
                            @php
                                $isSelected = $selectedDayDate && $day['date']->isSameDay($selectedDayDate);
                            @endphp
                            <div wire:key="day-{{ $day['date']->toDateString() }}"
                                x-data="{ expanded: false }"
                                @class([
                                    'border-b border-e border-ink-200',
                                    'bg-white' => $day['inMonth'],
                                    'bg-ink-100' => ! $day['inMonth'],
                                ])>

                                {{-- Phone: tappable day showing markers only --}}
                                <button type="button" wire:click="selectDay('{{ $day['date']->toDateString() }}')"
                                    @class([
                                        'flex h-14 w-full flex-col items-center justify-center gap-1.5 transition sm:hidden',
                                        'bg-accent-100' => $isSelected,
                                    ])>
                                    <span @class([
                                        'flex h-6 w-6 items-center justify-center text-[13px]',
                                        'bg-accent font-bold text-white' => $day['isToday'],
                                        'font-semibold text-ink-900' => ! $day['isToday'] && $day['inMonth'],
                                        'font-medium text-ink-400' => ! $day['isToday'] && ! $day['inMonth'],
                                    ])>{{ $day['date']->day }}</span>

                                    <span class="flex h-1.5 items-center gap-[3px]">
                                        @foreach ($day['events']->take(4) as $event)
                                            <span @class([
                                                'h-1.5 w-1.5',
                                                'bg-ink-900' => $event->type === App\Enums\EventType::Internal,
                                                'bg-accent' => $event->type === App\Enums\EventType::External,
                                            ])></span>
                                        @endforeach
                                    </span>
                                </button>

                                {{-- Desktop: day number with full event pills --}}
                                <div class="hidden min-h-28 flex-col gap-1 p-2 sm:flex">
                                    <span @class([
                                        'flex h-[22px] w-[22px] items-center justify-center text-xs',
                                        'bg-accent font-bold text-white' => $day['isToday'],
                                        'font-semibold text-ink-800' => ! $day['isToday'] && $day['inMonth'],
                                        'font-medium text-ink-400' => ! $day['isToday'] && ! $day['inMonth'],
                                    ])>{{ $day['date']->day }}</span>

                                    @foreach ($day['events'] as $index => $event)
                                        <button wire:click="selectEvent({{ $event->id }})"
                                            x-show="expanded || {{ $index }} < 3"
                                            @if ($index >= 3) x-cloak @endif
                                            @class([
                                                'block w-full truncate px-1.5 py-1 text-start text-[11px] font-semibold transition',
                                                'bg-ink-900 text-ink-100 hover:bg-ink' => $event->type === App\Enums\EventType::Internal,
                                                'bg-accent text-white hover:bg-accent-600' => $event->type === App\Enums\EventType::External,
                                            ])
                                            title="{{ $event->name }}">
                                            {{ $event->name }}
                                        </button>
                                    @endforeach

                                    @if ($day['events']->count() > 3)
                                        <button x-show="!expanded" @click="expanded = true"
                                            class="block w-full px-0.5 text-start text-[11px] font-bold text-accent-700 transition hover:text-accent">
                                            +{{ $day['events']->count() - 3 }} more
                                        </button>
                                        <button x-show="expanded" x-cloak @click="expanded = false"
                                            class="block w-full px-0.5 text-start text-[11px] font-bold text-accent-700 transition hover:text-accent">
                                            Show less
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Phone only: the tapped day's events, listed in full --}}
                <div class="hps-panel sm:hidden">
                    <div class="hps-panel-head">
                        <span class="hps-label text-ink">{{ $selectedDayDate?->format('D j F') }}</span>
                        <span class="text-[11px] font-semibold text-ink-600">
                            {{ trans_choice(':count event|:count events', $selectedDayEvents->count()) }}
                        </span>
                    </div>

                    @forelse ($selectedDayEvents as $event)
                        <button wire:click="selectEvent({{ $event->id }})" wire:key="agenda-{{ $event->id }}"
                            class="hps-row flex w-full items-start gap-2.5 p-3 text-start transition first:border-t-0 hover:bg-ink-100">
                            <span @class([
                                'w-1 shrink-0 self-stretch',
                                'bg-ink-900' => $event->type === App\Enums\EventType::Internal,
                                'bg-accent' => $event->type === App\Enums\EventType::External,
                            ])></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-ink">{{ $event->name }}</span>
                                <span class="mt-0.5 block text-xs text-ink-700">{{ $event->starts_at->format('g:ia') }} – {{ $event->ends_at->format('g:ia') }}</span>
                                @if ($event->location)
                                    <span class="block text-xs text-ink-500">{{ $event->location }}</span>
                                @endif
                            </span>
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-ink-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>
                        </button>
                    @empty
                        <p class="p-4 text-center text-sm text-ink-600">Nothing scheduled this day.</p>
                    @endforelse
                </div>
            </div>

            {{-- Upcoming events beyond this month --}}
            <div class="hps-panel self-start">
                <div class="hps-panel-head">
                    <span class="hps-label text-ink">Upcoming events</span>
                </div>

                @forelse ($upcoming as $event)
                    <button wire:click="selectEvent({{ $event->id }})" wire:key="upcoming-{{ $event->id }}"
                        class="hps-row flex w-full items-start gap-3 p-4 text-start transition first:border-t-0 hover:bg-ink-100">
                        <span class="w-10 shrink-0">
                            <span class="block text-[10px] font-bold uppercase tracking-label text-ink-500">{{ $event->starts_at->format('M') }}</span>
                            <span class="block text-xl font-extrabold leading-tight text-ink">{{ $event->starts_at->format('j') }}</span>
                        </span>
                        <span class="min-w-0">
                            <span class="flex items-center gap-2">
                                <span @class([
                                    'h-2 w-2 shrink-0',
                                    'bg-ink-900' => $event->type === App\Enums\EventType::Internal,
                                    'bg-accent' => $event->type === App\Enums\EventType::External,
                                ])></span>
                                <span class="truncate text-[13px] font-bold text-ink">{{ $event->name }}</span>
                            </span>
                            <span class="mt-0.5 block text-xs text-ink-700">{{ $event->starts_at->format('D j M, g:ia') }}</span>
                        </span>
                    </button>
                @empty
                    <p class="p-4 text-sm text-ink-600">No events beyond this month.</p>
                @endforelse
            </div>
        </div>

        {{-- Phone: full-width create action pinned under the content --}}
        @can('create', App\Models\Event::class)
            <x-primary-button wire:click="openCreate" type="button" class="w-full justify-start py-4 sm:hidden">
                + New Event
            </x-primary-button>
        @endcan
    </div>

    {{-- Event detail sheet --}}
    @if ($selectedEvent)
        <x-sheet :title="$selectedEvent->name"
            :badge="$selectedEvent->type->label()"
            :badge-class="$selectedEvent->type === App\Enums\EventType::Internal ? 'bg-ink-900 text-ink-100' : 'bg-accent text-white'"
            close="closeModal">

            <div class="flex gap-3 border-b border-ink-200 p-3.5">
                <span class="hps-label w-[74px] shrink-0 pt-0.5">Starts</span>
                <span class="text-[13px] font-semibold text-ink">{{ $selectedEvent->starts_at->format('D j M Y, g:ia') }}</span>
            </div>

            <div class="flex gap-3 border-b border-ink-200 p-3.5">
                <span class="hps-label w-[74px] shrink-0 pt-0.5">Ends</span>
                <span class="text-[13px] font-semibold text-ink">{{ $selectedEvent->ends_at->format('D j M Y, g:ia') }}</span>
            </div>

            @if ($selectedEvent->location)
                <div class="flex gap-3 border-b border-ink-200 p-3.5">
                    <span class="hps-label w-[74px] shrink-0 pt-0.5">Location</span>
                    <span class="text-[13px] font-semibold text-ink">{{ $selectedEvent->location }}</span>
                </div>
            @endif

            @if ($selectedEvent->details)
                <div class="flex flex-col gap-1.5 border-b border-ink-200 p-3.5">
                    <span class="hps-label">Details</span>
                    <p class="whitespace-pre-line text-[13px] leading-relaxed text-ink-800">{{ $selectedEvent->details }}</p>
                </div>
            @endif

            <div class="flex flex-col gap-2 p-3.5">
                <span class="hps-label">Assigned staff</span>
                @if ($selectedEvent->staff->isEmpty())
                    <span class="text-[13px] text-ink-600">No staff assigned.</span>
                @else
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($selectedEvent->staff as $member)
                            <span class="hps-chip">{{ $member->name }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            @can('update', $selectedEvent)
                <x-slot name="actions">
                    <x-dark-button wire:click="openEdit({{ $selectedEvent->id }})" class="flex-1 justify-start">
                        Edit Event
                    </x-dark-button>
                    <x-danger-button wire:click="delete({{ $selectedEvent->id }})"
                        wire:confirm="Delete this event? This cannot be undone." type="button">
                        Delete
                    </x-danger-button>
                </x-slot>
            @endcan
        </x-sheet>
    @endif

    {{-- Create / edit form --}}
    @if ($showForm)
        <x-sheet :title="$editingId ? 'Edit Event' : 'New Event'" close="$set('showForm', false)" wide>
            <form wire:submit="save" class="space-y-4 p-4">
                <div>
                    <x-input-label for="event-name" value="Name" />
                    <x-text-input wire:model="name" id="event-name" type="text" class="mt-1" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Type" />
                    <div class="mt-1 flex">
                        <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 border border-ink-400 py-2.5 text-[13px] font-semibold transition has-[:checked]:bg-ink-900 has-[:checked]:text-ink-100">
                            <input type="radio" wire:model="type" value="internal" class="sr-only">
                            <span class="h-2.5 w-2.5 bg-ink-900"></span> Internal
                        </label>
                        <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 border border-l-0 border-ink-400 py-2.5 text-[13px] font-semibold transition has-[:checked]:bg-accent has-[:checked]:text-white">
                            <input type="radio" wire:model="type" value="external" class="sr-only">
                            <span class="h-2.5 w-2.5 bg-accent"></span> External
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('type')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <x-input-label for="starts_at" value="Starts" />
                        <x-text-input wire:model.live="starts_at" id="starts_at" type="datetime-local" class="mt-1" />
                        <x-input-error :messages="$errors->get('starts_at')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="ends_at" value="Ends" />
                        <x-text-input wire:model="ends_at" id="ends_at" type="datetime-local" class="mt-1" />
                        <x-input-error :messages="$errors->get('ends_at')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="event-location" value="Location" />
                    <x-text-input wire:model="location" id="event-location" type="text" class="mt-1" placeholder="e.g. HPS Main Hall, Taurama Aquatic Centre" />
                    <x-input-error :messages="$errors->get('location')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="details" value="Details" />
                    <textarea wire:model="details" id="details" rows="3"
                        class="mt-1 block w-full border border-ink-400 bg-surface px-2.5 py-1.5 text-sm text-ink caret-accent focus:border-accent focus:ring-0"></textarea>
                    <x-input-error :messages="$errors->get('details')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Assign staff" />
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
                    <x-primary-button>{{ $editingId ? 'Save Changes' : 'Create Event' }}</x-primary-button>
                </div>
            </form>
        </x-sheet>
    @endif
</div>
