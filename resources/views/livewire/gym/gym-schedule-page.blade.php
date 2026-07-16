<div class="py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        {{-- Header: date nav + legend + new session --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button wire:click="previousDay" class="p-2 rounded-md hover:bg-gray-200 text-gray-600" aria-label="Previous day">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <input type="date" wire:model.live="date"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                <button wire:click="nextDay" class="p-2 rounded-md hover:bg-gray-200 text-gray-600" aria-label="Next day">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                <button wire:click="goToday" class="ms-1 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide rounded-md border border-gray-300 text-gray-600 hover:bg-gray-100">
                    Today
                </button>
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3 text-xs text-gray-600">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-violet-500"></span> Studio 1</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-teal-500"></span> Studio 2</span>
                </div>

                @can('create', App\Models\GymSchedule::class)
                    <button wire:click="openCreate"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        + New Session
                    </button>
                @endcan
            </div>
        </div>

        <h2 class="font-semibold text-lg text-gray-800">{{ $day->format('l, j F Y') }}</h2>

        {{-- Timeline --}}
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <div class="flex">
                {{-- Hour labels --}}
                <div class="w-12 sm:w-16 shrink-0 relative" style="height: 720px;">
                    @foreach ($hours as $hour)
                        <div class="absolute w-full text-end pe-2 text-[10px] sm:text-xs text-gray-400 -translate-y-1/2"
                            style="top: {{ ($hour - 7) / 12 * 100 }}%;">
                            {{ $hour <= 12 ? $hour : $hour - 12 }}{{ $hour < 12 ? 'am' : 'pm' }}
                        </div>
                    @endforeach
                </div>

                {{-- Blocks area --}}
                <div class="flex-1 relative border-s border-gray-100 me-2" style="height: 720px;">
                    @foreach ($hours as $hour)
                        <div class="absolute w-full border-t border-gray-100" style="top: {{ ($hour - 7) / 12 * 100 }}%;"></div>
                    @endforeach

                    @if (empty($blocks))
                        <p class="absolute inset-0 flex items-center justify-center text-sm text-gray-400">
                            No sessions scheduled this day.
                        </p>
                    @endif

                    @foreach ($blocks as $block)
                        @php $session = $block['session']; @endphp
                        <div wire:key="session-{{ $session->id }}"
                            @class([
                                'absolute rounded-md border-s-4 shadow-sm p-1.5 sm:p-2 overflow-hidden text-xs',
                                'bg-violet-50 border-violet-500' => $session->studio === '1',
                                'bg-teal-50 border-teal-500' => $session->studio === '2',
                            ])
                            style="top: {{ $block['top'] }}%; height: {{ $block['height'] }}%; left: calc({{ ($block['column'] / $block['columns']) * 100 }}% + 2px); width: calc({{ (1 / $block['columns']) * 100 }}% - 6px);">
                            <div class="flex items-start justify-between gap-1">
                                <div class="min-w-0">
                                    <span @class([
                                        'inline-flex rounded px-1 py-px text-[9px] sm:text-[10px] font-bold text-white',
                                        'bg-violet-500' => $session->studio === '1',
                                        'bg-teal-500' => $session->studio === '2',
                                    ])>
                                        S{{ $session->studio }}
                                    </span>
                                    <span class="font-semibold text-gray-800 block truncate">{{ $session->name }}</span>
                                </div>

                                @can('update', $session)
                                    <div class="flex gap-1 shrink-0">
                                        <button wire:click="openEdit({{ $session->id }})" class="text-gray-400 hover:text-gray-700" aria-label="Edit session">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button wire:click="delete({{ $session->id }})"
                                            wire:confirm="Delete this session? This deletes the WHOLE series (every occurrence)."
                                            class="text-gray-400 hover:text-red-600" aria-label="Delete session">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                @endcan
                            </div>

                            <div class="text-gray-600 truncate">{{ $session->client_name }}</div>
                            <div class="text-gray-500">
                                {{ substr($session->start_time, 0, 5) }}–{{ substr($session->end_time, 0, 5) }}
                            </div>
                            @if ($session->staff->isNotEmpty())
                                <div class="text-gray-500 truncate">
                                    👤 {{ $session->staff->pluck('name')->join(', ') }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Create / edit form modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/50" wire:click="$set('showForm', false)"></div>

            <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit Session (whole series)' : 'New Session' }}</h3>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <x-input-label for="gs-name" value="Session name" />
                        <x-text-input wire:model="name" id="gs-name" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="client_type" value="Client type" />
                            <select wire:model="client_type" id="client_type"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                <option value="national_federation">National Federation</option>
                                <option value="external_client">External Client</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="client_name" value="Client name" />
                            <x-text-input wire:model="client_name" id="client_name" type="text" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('client_name')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label value="Studio" />
                        <div class="mt-1 flex gap-4 text-sm">
                            <label class="flex items-center gap-1.5">
                                <input type="radio" wire:model="studio" value="1" class="text-violet-500 focus:ring-violet-500">
                                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-violet-500"></span> Studio 1</span>
                            </label>
                            <label class="flex items-center gap-1.5">
                                <input type="radio" wire:model="studio" value="2" class="text-teal-500 focus:ring-teal-500">
                                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-teal-500"></span> Studio 2</span>
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('studio')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="gs-start_date" value="Start date" />
                            <x-text-input wire:model="start_date" id="gs-start_date" type="date" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('start_date')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="gs-end_date" value="End date" />
                            <x-text-input wire:model="end_date" id="gs-end_date" type="date" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('end_date')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="gs-start_time" value="Start time" />
                            <x-text-input wire:model="start_time" id="gs-start_time" type="time" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('start_time')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="gs-end_time" value="Finish time" />
                            <x-text-input wire:model="end_time" id="gs-end_time" type="time" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('end_time')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="recurrence" value="Repeats" />
                        <select wire:model.live="recurrence" id="recurrence"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            <option value="none">Does not repeat</option>
                            <option value="daily">Daily (every day between the dates)</option>
                            <option value="weekly">Weekly (selected weekdays)</option>
                        </select>
                        <x-input-error :messages="$errors->get('recurrence')" class="mt-1" />
                    </div>

                    @if ($recurrence === 'weekly')
                        <div>
                            <x-input-label value="On weekdays" />
                            <div class="mt-1 flex flex-wrap gap-2">
                                @foreach ($weekdays as $value => $label)
                                    <label class="flex items-center gap-1 text-sm border border-gray-200 rounded-md px-2 py-1">
                                        <input type="checkbox" wire:model="days_of_week" value="{{ $value }}"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('days_of_week')" class="mt-1" />
                        </div>
                    @endif

                    <div>
                        <x-input-label value="Allocate staff" />
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
                        <x-primary-button>{{ $editingId ? 'Save Changes' : 'Create Session' }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
