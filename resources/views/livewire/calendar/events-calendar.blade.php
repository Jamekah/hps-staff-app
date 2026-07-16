<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        {{-- Header: month nav + legend + new event --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button wire:click="previousMonth" class="p-2 rounded-md hover:bg-gray-200 text-gray-600" aria-label="Previous month">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <h2 class="font-semibold text-xl text-gray-800 w-44 text-center">{{ $monthLabel }}</h2>
                <button wire:click="nextMonth" class="p-2 rounded-md hover:bg-gray-200 text-gray-600" aria-label="Next month">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                <button wire:click="goToday" class="ms-1 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide rounded-md border border-gray-300 text-gray-600 hover:bg-gray-100">
                    Today
                </button>
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3 text-xs text-gray-600">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-sky-500"></span> Internal</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-amber-500"></span> External</span>
                </div>

                @can('create', App\Models\Event::class)
                    <button wire:click="openCreate"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        + New Event
                    </button>
                @endcan
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
            {{-- Month grid --}}
            <div class="lg:col-span-3 bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 text-[11px] sm:text-xs font-semibold uppercase tracking-wide text-gray-500">
                    @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)
                        <div class="px-1 sm:px-2 py-2 text-center">{{ $dow }}</div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7">
                    @foreach ($days as $day)
                        <div wire:key="day-{{ $day['date']->toDateString() }}"
                            x-data="{ expanded: false }"
                            class="min-h-20 sm:min-h-28 border-b border-e border-gray-100 p-1 {{ $day['inMonth'] ? 'bg-white' : 'bg-gray-50' }}">
                            <div class="flex justify-center sm:justify-start">
                                <span @class([
                                    'inline-flex items-center justify-center w-6 h-6 text-xs rounded-full',
                                    'bg-gray-800 text-white font-bold' => $day['isToday'],
                                    'text-gray-700' => ! $day['isToday'] && $day['inMonth'],
                                    'text-gray-400' => ! $day['isToday'] && ! $day['inMonth'],
                                ])>
                                    {{ $day['date']->day }}
                                </span>
                            </div>

                            <div class="mt-1 space-y-0.5">
                                @foreach ($day['events'] as $index => $event)
                                    <button wire:click="selectEvent({{ $event->id }})"
                                        x-show="expanded || {{ $index }} < 3"
                                        @if ($index >= 3) x-cloak @endif
                                        @class([
                                            'block w-full text-start truncate rounded px-1 sm:px-1.5 py-0.5 text-[10px] sm:text-xs font-medium text-white',
                                            'bg-sky-500 hover:bg-sky-600' => $event->type === App\Enums\EventType::Internal,
                                            'bg-amber-500 hover:bg-amber-600' => $event->type === App\Enums\EventType::External,
                                        ])
                                        title="{{ $event->name }}">
                                        {{ $event->name }}
                                    </button>
                                @endforeach

                                @if ($day['events']->count() > 3)
                                    <button x-show="!expanded" @click="expanded = true"
                                        class="block w-full text-start px-1.5 text-[10px] sm:text-xs font-semibold text-gray-500 hover:text-gray-700">
                                        +{{ $day['events']->count() - 3 }} more
                                    </button>
                                    <button x-show="expanded" x-cloak @click="expanded = false"
                                        class="block w-full text-start px-1.5 text-[10px] sm:text-xs font-semibold text-gray-500 hover:text-gray-700">
                                        Show less
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Upcoming events sidebar --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-3">Upcoming events</h3>
                @forelse ($upcoming as $event)
                    <button wire:click="selectEvent({{ $event->id }})" wire:key="upcoming-{{ $event->id }}"
                        class="block w-full text-start py-2 border-b border-gray-100 last:border-0 hover:bg-gray-50 rounded px-1">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full shrink-0 {{ $event->type === App\Enums\EventType::Internal ? 'bg-sky-500' : 'bg-amber-500' }}"></span>
                            <span class="text-sm font-medium text-gray-800 truncate">{{ $event->name }}</span>
                        </div>
                        <div class="text-xs text-gray-500 ms-4">{{ $event->starts_at->format('D j M Y, g:ia') }}</div>
                    </button>
                @empty
                    <p class="text-sm text-gray-500">No events beyond this month.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Event detail modal --}}
    @if ($selectedEvent)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/50" wire:click="closeModal"></div>

            <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <span @class([
                            'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold text-white mb-2',
                            'bg-sky-500' => $selectedEvent->type === App\Enums\EventType::Internal,
                            'bg-amber-500' => $selectedEvent->type === App\Enums\EventType::External,
                        ])>
                            {{ $selectedEvent->type->label() }}
                        </span>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $selectedEvent->name }}</h3>
                    </div>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="text-sm text-gray-700 space-y-2">
                    <p>
                        <span class="font-semibold">Starts:</span> {{ $selectedEvent->starts_at->format('D j M Y, g:ia') }}<br>
                        <span class="font-semibold">Ends:</span> {{ $selectedEvent->ends_at->format('D j M Y, g:ia') }}
                    </p>

                    @if ($selectedEvent->details)
                        <p class="whitespace-pre-line">{{ $selectedEvent->details }}</p>
                    @endif

                    <div>
                        <span class="font-semibold">Assigned staff:</span>
                        @if ($selectedEvent->staff->isEmpty())
                            <span class="text-gray-500">none</span>
                        @else
                            <ul class="mt-1 flex flex-wrap gap-1.5">
                                @foreach ($selectedEvent->staff as $member)
                                    <li class="bg-gray-100 text-gray-700 rounded-full px-2.5 py-0.5 text-xs">{{ $member->name }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                @can('update', $selectedEvent)
                    <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                        <button wire:click="delete({{ $selectedEvent->id }})"
                            wire:confirm="Delete this event? This cannot be undone."
                            class="px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-800">
                            Delete
                        </button>
                        <button wire:click="openEdit({{ $selectedEvent->id }})"
                            class="px-4 py-1.5 text-sm font-medium rounded-md bg-gray-800 text-white hover:bg-gray-700">
                            Edit
                        </button>
                    </div>
                @endcan
            </div>
        </div>
    @endif

    {{-- Create / edit form modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/50" wire:click="$set('showForm', false)"></div>

            <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit Event' : 'New Event' }}</h3>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <x-input-label for="event-name" value="Name" />
                        <x-text-input wire:model="name" id="event-name" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Type" />
                        <div class="mt-1 flex gap-4 text-sm">
                            <label class="flex items-center gap-1.5">
                                <input type="radio" wire:model="type" value="internal" class="text-sky-500 focus:ring-sky-500">
                                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-sky-500"></span> Internal</span>
                            </label>
                            <label class="flex items-center gap-1.5">
                                <input type="radio" wire:model="type" value="external" class="text-amber-500 focus:ring-amber-500">
                                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> External</span>
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('type')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="starts_at" value="Starts" />
                            <x-text-input wire:model="starts_at" id="starts_at" type="datetime-local" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('starts_at')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="ends_at" value="Ends" />
                            <x-text-input wire:model="ends_at" id="ends_at" type="datetime-local" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('ends_at')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="details" value="Details" />
                        <textarea wire:model="details" id="details" rows="3"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                        <x-input-error :messages="$errors->get('details')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Assign staff" />
                        <div class="mt-1 max-h-40 overflow-y-auto border border-gray-200 rounded-md p-2 space-y-1">
                            @foreach ($activeStaff as $member)
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" wire:model="staffIds" value="{{ $member->id }}"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    {{ $member->name }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('staffIds')" class="mt-1" />
                        <x-input-error :messages="collect($errors->get('staffIds.*'))->flatten()->all()" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button wire:click="$set('showForm', false)" type="button">Cancel</x-secondary-button>
                        <x-primary-button>{{ $editingId ? 'Save Changes' : 'Create Event' }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
