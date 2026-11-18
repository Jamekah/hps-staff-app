{{--
    Staff Availability. Admin-only: the leave *type* and notes appear on this
    page and nowhere else in the app.

    Amber/ochre is this module's hue (tailwind `leave`), kept clear of the
    calendar's ink/red, the gym's blue/green and the clinic's plum.
--}}
<div class="py-4 sm:py-6">
    <div class="mx-auto max-w-7xl space-y-4 px-3 sm:px-6 lg:px-8">

        {{-- ================= Header ================= --}}
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-bold tracking-tight text-ink sm:text-2xl">Staff Availability</h2>
            <x-primary-button wire:click="openLeaveForm" type="button" class="hidden sm:inline-flex">
                + Record leave
            </x-primary-button>
        </div>

        <x-flash />

        {{-- Tabs. Remembered for the session, so coming back lands where you left. --}}
        <div class="hps-segment w-full sm:w-auto sm:self-start">
            @foreach (['overview' => 'Overview', 'month' => 'Month', 'staff' => 'Staff'] as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')"
                    @class([
                        'flex-1 border-e border-ink-400 px-4 py-2 text-xs font-semibold uppercase tracking-label transition last:border-e-0 sm:flex-none',
                        'bg-leave text-white' => $tab === $key,
                        'bg-white text-ink-700 hover:bg-ink-100' => $tab !== $key,
                    ])>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- ================= Overview ================= --}}
        @if ($tab === 'overview')
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                {{-- Away today --}}
                <div class="hps-panel">
                    <div class="hps-panel-head">
                        <h3 class="text-sm font-bold tracking-tight text-ink">Away today</h3>
                        <span class="hps-label">{{ $awayToday->count() }}</span>
                    </div>

                    @forelse ($awayToday as $leave)
                        <button type="button" wire:key="away-{{ $leave->id }}"
                            wire:click="openStaffDetail({{ $leave->user_id }})"
                            class="hps-row flex w-full min-h-11 items-start justify-between gap-3 p-3.5 text-start transition first:border-t-0 hover:bg-leave-tint">
                            <span class="min-w-0">
                                <span class="block truncate text-[13px] font-semibold text-ink">{{ $leave->user?->name }}</span>
                                @if ($leave->user?->position)
                                    <span class="block truncate text-xs text-ink-600">{{ $leave->user->position }}</span>
                                @endif
                                <span class="mt-1 inline-block rounded-sm border border-leave-muted px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-label text-leave-dark">
                                    {{ $leave->type->shortLabel() }}
                                </span>
                            </span>
                            <span class="shrink-0 text-end">
                                <span class="block text-[13px] font-semibold text-leave-dark">Back {{ $leave->return_date->format('D j M') }}</span>
                                <span class="block text-xs text-ink-600">
                                    {{ \App\Services\WorkingDays::between(now()->startOfDay(), $leave->return_date) }} working days left
                                </span>
                            </span>
                        </button>
                    @empty
                        <p class="p-6 text-center text-sm text-ink-600">Everyone's in today.</p>
                    @endforelse
                </div>

                {{-- Returning in the next 7 days --}}
                <div class="hps-panel">
                    <div class="hps-panel-head">
                        <h3 class="text-sm font-bold tracking-tight text-ink">Returning in the next 7 days</h3>
                        <span class="hps-label">{{ $returningSoon->count() }}</span>
                    </div>

                    @forelse ($returningSoon as $leave)
                        <div wire:key="ret-{{ $leave->id }}" class="hps-row flex items-center justify-between gap-3 p-3.5 first:border-t-0">
                            <span class="min-w-0">
                                <span class="block truncate text-[13px] font-semibold text-ink">{{ $leave->user?->name }}</span>
                                @if ($leave->user?->position)
                                    <span class="block truncate text-xs text-ink-600">{{ $leave->user->position }}</span>
                                @endif
                            </span>
                            <span class="shrink-0 text-[13px] font-semibold text-leave-dark">
                                {{ $leave->return_date->isToday() ? 'Back today' : 'Back '.$leave->return_date->format('D j M') }}
                            </span>
                        </div>
                    @empty
                        <p class="p-6 text-center text-sm text-ink-600">Nobody is due back this week.</p>
                    @endforelse
                </div>

                {{-- Upcoming leave --}}
                <div class="hps-panel">
                    <div class="hps-panel-head">
                        <h3 class="text-sm font-bold tracking-tight text-ink">Upcoming leave (next 30 days)</h3>
                        <span class="hps-label">{{ $upcomingLeave->count() }}</span>
                    </div>

                    @forelse ($upcomingLeave as $leave)
                        <button type="button" wire:key="up-{{ $leave->id }}"
                            wire:click="openStaffDetail({{ $leave->user_id }})"
                            class="hps-row flex w-full min-h-11 items-center justify-between gap-3 p-3.5 text-start transition first:border-t-0 hover:bg-leave-tint">
                            <span class="min-w-0">
                                <span class="block truncate text-[13px] font-semibold text-ink">{{ $leave->user?->name }}</span>
                                <span class="mt-1 inline-block rounded-sm border border-leave-muted px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-label text-leave-dark">
                                    {{ $leave->type->shortLabel() }}
                                </span>
                            </span>
                            <span class="shrink-0 text-end text-[13px] font-semibold text-ink">
                                {{ $leave->first_day->format('j M') }} – {{ $leave->lastDay()->format('j M') }}
                                <span class="block text-xs font-normal text-ink-600">back {{ $leave->return_date->format('D j M') }}</span>
                            </span>
                        </button>
                    @empty
                        <p class="p-6 text-center text-sm text-ink-600">No leave booked in the next 30 days.</p>
                    @endforelse
                </div>

                {{-- Assignment warnings: already-assigned work that now falls in someone's leave --}}
                <div class="hps-panel">
                    <div class="hps-panel-head">
                        <h3 class="text-sm font-bold tracking-tight text-ink">Assignment warnings</h3>
                        <span class="hps-label">{{ count($warnings) }}</span>
                    </div>

                    @forelse (array_slice($warnings, 0, 20) as $warning)
                        <a href="{{ $warning['link'] }}" wire:navigate
                            wire:key="warn-{{ $loop->index }}"
                            class="hps-row flex min-h-11 items-start justify-between gap-3 p-3.5 transition first:border-t-0 hover:bg-leave-tint">
                            <span class="min-w-0">
                                <span class="block truncate text-[13px] font-semibold text-ink">{{ $warning['title'] }}</span>
                                <span class="block truncate text-xs text-ink-600">
                                    {{ $warning['person'] }} is away · back {{ $warning['back']->format('j M') }}
                                </span>
                            </span>
                            <span class="shrink-0 text-end">
                                <span class="block text-xs font-semibold text-ink">{{ $warning['when']->format('D j M') }}</span>
                                <span @class([
                                    'mt-1 inline-block rounded-sm px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-label',
                                    'bg-ink-900 text-ink-100' => $warning['type'] === 'event',
                                    'bg-studio1 text-white' => $warning['type'] === 'gym',
                                    'bg-clinic text-white' => $warning['type'] === 'clinic',
                                ])>{{ $warning['type'] }}</span>
                            </span>
                        </a>
                    @empty
                        <p class="p-6 text-center text-sm text-ink-600">Nothing clashes with anyone's leave.</p>
                    @endforelse

                    @if (count($warnings) > 20)
                        <p class="hps-row p-3 text-center text-xs text-ink-600">
                            and {{ count($warnings) - 20 }} more in the next 30 days
                        </p>
                    @endif
                </div>
            </div>
        @endif

        {{-- ================= Month ================= --}}
        @if ($tab === 'month')
            {{-- Desktop: the team grid. Hidden on phones, where it can't fit. --}}
            <div class="hidden sm:block">
                <div class="hps-panel">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b-2 border-ink px-4 py-3">
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="previousMonth" aria-label="Previous month"
                                class="rounded-lg border border-ink-400 px-2.5 py-1.5 text-ink-800 transition hover:bg-ink-100">&larr;</button>
                            <span class="min-w-[9rem] text-center text-sm font-bold tracking-tight text-ink">{{ $monthLabel }}</span>
                            <button type="button" wire:click="nextMonth" aria-label="Next month"
                                class="rounded-lg border border-ink-400 px-2.5 py-1.5 text-ink-800 transition hover:bg-ink-100">&rarr;</button>
                            <button type="button" wire:click="goToday"
                                class="rounded-lg border border-ink-400 px-2.5 py-1.5 text-[11px] font-semibold uppercase tracking-label text-ink-800 transition hover:bg-ink-100">Today</button>
                        </div>

                        <div class="flex items-center gap-2">
                            <select wire:model.live="positionFilter"
                                class="rounded-lg border border-ink-400 bg-surface px-2.5 py-1.5 text-xs text-ink focus:border-leave focus:ring-0">
                                <option value="">All positions</option>
                                @foreach ($positions as $position)
                                    <option value="{{ $position }}">{{ $position }}</option>
                                @endforeach
                            </select>

                            <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-ink-700">
                                <input type="checkbox" wire:model.live="showEveryone" class="border-ink-400 text-leave focus:ring-leave">
                                Show everyone
                            </label>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full border-collapse text-xs">
                            <thead>
                                <tr>
                                    <th class="hps-label sticky left-0 z-10 bg-white px-3 py-2 text-start">Staff</th>
                                    @foreach ($grid['days'] as $day)
                                        <th @class([
                                            'w-7 px-0 py-2 text-center text-[10px] font-semibold',
                                            'bg-ink-100 text-ink-600' => $day->isWeekend(),
                                            'text-ink-700' => ! $day->isWeekend(),
                                            'text-accent' => $day->isToday(),
                                        ])>
                                            <span class="block">{{ $day->format('j') }}</span>
                                            <span class="block text-[9px] font-normal text-ink-500">{{ substr($day->format('D'), 0, 1) }}</span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($grid['rows'] as $row)
                                    <tr wire:key="grid-{{ $row['user']->id }}" class="border-t border-ink-200">
                                        <td class="sticky left-0 z-10 max-w-[12rem] bg-white px-3 py-1.5">
                                            <button type="button" wire:click="openStaffDetail({{ $row['user']->id }})"
                                                class="block max-w-full truncate text-start text-[12px] font-semibold text-ink transition hover:text-leave-dark">
                                                {{ $row['user']->name }}
                                                @if ($row['user']->position)
                                                    <span class="block truncate text-[10px] font-normal text-ink-600">{{ $row['user']->position }}</span>
                                                @endif
                                            </button>
                                        </td>

                                        @foreach ($row['cells'] as $cell)
                                            <td @class([
                                                'h-8 border-s border-ink-200 px-0 py-1.5',
                                                'bg-ink-100' => $cell['isWeekend'] && ! $cell['leave'],
                                            ])>
                                                @if ($cell['leave'])
                                                    <div
                                                        title="{{ $cell['leave']->type->label() }} — back {{ $cell['leave']->return_date->format('j M') }}"
                                                        @class([
                                                            'h-5 bg-leave',
                                                            'rounded-s-sm' => $cell['startsHere'],
                                                            'rounded-e-sm' => $cell['endsHere'],
                                                            'opacity-70' => $cell['isWeekend'],
                                                        ])></div>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($grid['days']) + 1 }}" class="px-4 py-10 text-center text-sm text-ink-600">
                                            No leave recorded this month. Tick "Show everyone" to see the full team.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center gap-3 border-t border-ink-200 px-4 py-3">
                        <span class="flex items-center gap-1.5 text-[11px] font-semibold text-ink-700">
                            <span class="h-3 w-5 rounded-sm bg-leave"></span> On leave
                        </span>
                        <span class="flex items-center gap-1.5 text-[11px] font-semibold text-ink-700">
                            <span class="h-3 w-5 rounded-sm bg-ink-100"></span> Weekend
                        </span>
                    </div>
                </div>
            </div>

            {{-- Phone: a 7-day strip instead of a grid that cannot fit. --}}
            <div class="sm:hidden">
                <div class="hps-panel">
                    <div class="flex items-center justify-between gap-2 border-b-2 border-ink px-3 py-2.5">
                        <button type="button" wire:click="previousWeek" aria-label="Previous week"
                            class="min-h-11 min-w-11 rounded-lg border border-ink-400 px-3 text-ink-800">&larr;</button>
                        <span class="text-xs font-bold tracking-tight text-ink">{{ $selectedDate->format('F Y') }}</span>
                        <button type="button" wire:click="nextWeek" aria-label="Next week"
                            class="min-h-11 min-w-11 rounded-lg border border-ink-400 px-3 text-ink-800">&rarr;</button>
                    </div>

                    <div class="grid grid-cols-7 border-b border-ink-200">
                        @foreach ($strip as $day)
                            <button type="button" wire:key="strip-{{ $day->toDateString() }}"
                                wire:click="selectDay('{{ $day->toDateString() }}')"
                                @class([
                                    'flex min-h-[3.25rem] flex-col items-center justify-center border-e border-ink-200 py-1.5 last:border-e-0',
                                    'bg-leave text-white' => $day->isSameDay($selectedDate),
                                    'bg-ink-100 text-ink-700' => ! $day->isSameDay($selectedDate) && $day->isWeekend(),
                                    'text-ink-800' => ! $day->isSameDay($selectedDate) && ! $day->isWeekend(),
                                ])>
                                <span class="text-[10px] uppercase tracking-label">{{ substr($day->format('D'), 0, 1) }}</span>
                                <span class="text-sm font-bold">{{ $day->format('j') }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="px-3 py-2">
                        <span class="hps-label">Away on {{ $selectedDate->format('D j M') }}</span>
                    </div>

                    @forelse ($awayOnSelectedDay as $leave)
                        <button type="button" wire:key="day-{{ $leave->id }}"
                            wire:click="openStaffDetail({{ $leave->user_id }})"
                            class="hps-row flex w-full min-h-11 items-center justify-between gap-3 p-3.5 text-start">
                            <span class="min-w-0">
                                <span class="block truncate text-[13px] font-semibold text-ink">{{ $leave->user?->name }}</span>
                                @if ($leave->user?->position)
                                    <span class="block truncate text-xs text-ink-600">{{ $leave->user->position }}</span>
                                @endif
                            </span>
                            <span class="shrink-0 text-xs font-semibold text-leave-dark">Back {{ $leave->return_date->format('j M') }}</span>
                        </button>
                    @empty
                        <p class="p-6 text-center text-sm text-ink-600">Everyone's in that day.</p>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- ================= Staff (roster) ================= --}}
        @if ($tab === 'staff')
            <div class="hps-panel">
                <div class="flex flex-col gap-3 border-b border-ink-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input type="text" wire:model.live.debounce.300ms="staffSearch" placeholder="Search name or position…"
                            class="min-h-11 w-full rounded-lg border border-ink-400 bg-surface px-2.5 py-2 text-sm text-ink caret-leave placeholder:text-ink-500 focus:border-leave focus:ring-0 sm:min-h-0 sm:w-64">

                        <select wire:model.live="staffPositionFilter"
                            class="min-h-11 w-full rounded-lg border border-ink-400 bg-surface px-2.5 py-2 text-sm text-ink focus:border-leave focus:ring-0 sm:min-h-0 sm:w-48">
                            <option value="">All positions</option>
                            @foreach ($positions as $position)
                                <option value="{{ $position }}">{{ $position }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <x-dark-button wire:click="openStaffForm" type="button" class="flex-1 justify-center sm:flex-none">Add staff</x-dark-button>
                        <x-secondary-button wire:click="openImport" type="button" class="flex-1 justify-center sm:flex-none">Import list</x-secondary-button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-ink">
                                <th class="hps-label px-4 py-3 text-start">Name</th>
                                <th class="hps-label hidden px-4 py-3 text-start sm:table-cell">Account</th>
                                <th class="hps-label hidden px-4 py-3 text-start sm:table-cell">Status</th>
                                <th class="hps-label hidden px-4 py-3 text-end sm:table-cell">Leave this year</th>
                                <th class="hps-label hidden px-4 py-3 text-start lg:table-cell">Next leave</th>
                                <th class="hps-label px-4 py-3 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($staff as $member)
                                <tr wire:key="staff-{{ $member->id }}" class="border-b border-ink-200 transition hover:bg-ink-100">
                                    <td class="px-4 py-3">
                                        <button type="button" wire:click="openStaffDetail({{ $member->id }})"
                                            class="text-start font-semibold text-ink transition hover:text-leave-dark">
                                            {{ $member->name }}
                                        </button>
                                        <div class="text-xs text-ink-600">{{ $member->position ?? '—' }}</div>
                                        <div class="text-xs text-ink-600 sm:hidden">
                                            {{ $staffYearDays[$member->id] ?? 0 }} working
                                            {{ Str::plural('day', $staffYearDays[$member->id] ?? 0) }} of leave this year
                                        </div>
                                        <div class="mt-1 flex flex-wrap gap-1 sm:hidden">
                                            @if ($member->isRosterOnly())
                                                <span class="rounded-sm border border-leave-muted px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-label text-leave-dark">No login</span>
                                            @else
                                                <span class="rounded-sm border border-ink-400 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-label text-ink-700">Login</span>
                                            @endif
                                            @unless ($member->is_active)
                                                <span class="rounded-sm border border-accent px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-label text-accent-700">Inactive</span>
                                            @endunless
                                        </div>
                                    </td>

                                    <td class="hidden px-4 py-3 sm:table-cell">
                                        @if ($member->isRosterOnly())
                                            <span class="inline-flex rounded-sm border border-leave-muted px-2 py-1 text-[10px] font-semibold uppercase tracking-label text-leave-dark">No login</span>
                                        @else
                                            <span class="inline-flex rounded-sm border border-ink-400 px-2 py-1 text-[10px] font-semibold uppercase tracking-label text-ink-700">Login</span>
                                        @endif
                                    </td>

                                    <td class="hidden px-4 py-3 sm:table-cell">
                                        <span @class([
                                            'inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-label',
                                            'text-studio2' => $member->is_active,
                                            'text-accent-700' => ! $member->is_active,
                                        ])>
                                            <span @class([
                                                'h-2 w-2 rounded-full',
                                                'bg-studio2' => $member->is_active,
                                                'bg-accent' => ! $member->is_active,
                                            ])></span>
                                            {{ $member->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>

                                    <td class="hidden px-4 py-3 text-end font-semibold text-ink sm:table-cell">
                                        {{ $staffYearDays[$member->id] ?? 0 }}
                                        <span class="block text-[10px] font-normal uppercase tracking-label text-ink-500">working days</span>
                                    </td>

                                    <td class="hidden px-4 py-3 text-ink-700 lg:table-cell">
                                        @if ($next = $nextLeave->get($member->id))
                                            <span class="text-xs font-semibold text-leave-dark">{{ $next->first_day->format('j M') }}</span>
                                            <span class="block text-[11px] text-ink-600">back {{ $next->return_date->format('j M') }}</span>
                                        @else
                                            <span class="text-xs text-ink-500">—</span>
                                        @endif
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-3 text-end">
                                        <div class="flex flex-wrap justify-end gap-x-3 gap-y-1">
                                            <button wire:click="openLeaveForm({{ $member->id }})"
                                                class="inline-flex items-center py-3 text-[11px] font-semibold uppercase tracking-label text-leave-dark transition hover:text-leave sm:py-0">Leave</button>

                                            @can('update', $member)
                                                <button wire:click="openStaffForm({{ $member->id }})"
                                                    class="inline-flex items-center py-3 text-[11px] font-semibold uppercase tracking-label text-ink-700 transition hover:text-ink sm:py-0">Edit</button>
                                            @endcan

                                            @can('toggleActive', $member)
                                                <button wire:click="toggleStaffActive({{ $member->id }})"
                                                    class="inline-flex items-center py-3 text-[11px] font-semibold uppercase tracking-label text-ink-700 transition hover:text-ink sm:py-0">
                                                    {{ $member->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-ink-600">No staff found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Phone: the primary action sits at the bottom, as elsewhere in the app. --}}
        <x-primary-button wire:click="openLeaveForm" type="button" class="w-full justify-start py-4 sm:hidden">
            + Record leave
        </x-primary-button>
    </div>

    {{-- ================= Record leave sheet ================= --}}
    @if ($showLeaveForm)
        <x-sheet :title="$editingLeaveId ? 'Edit leave' : 'Record leave'" close="$set('showLeaveForm', false)" wide>
            <form wire:submit="saveLeave" class="space-y-4 p-4">

                {{-- 1. Staff --}}
                <div>
                    <x-input-label value="Staff" />
                    @if ($leaveStaffMember)
                        <div class="mt-1 flex min-h-11 items-center justify-between gap-3 rounded-lg border border-ink-400 bg-surface px-3 py-2">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-ink">{{ $leaveStaffMember->name }}</span>
                                @if ($leaveStaffMember->position)
                                    <span class="block truncate text-xs text-ink-600">{{ $leaveStaffMember->position }}</span>
                                @endif
                            </span>
                            <button type="button" wire:click="$set('leaveUserId', null)"
                                class="shrink-0 text-[11px] font-semibold uppercase tracking-label text-accent-700 hover:text-accent">Change</button>
                        </div>
                    @else
                        <input type="text" wire:model.live.debounce.300ms="leaveStaffSearch" placeholder="Search staff…"
                            class="mt-1 min-h-11 w-full rounded-lg border border-ink-400 bg-surface px-2.5 py-2 text-sm text-ink caret-leave placeholder:text-ink-500 focus:border-leave focus:ring-0">

                        <div class="mt-1 max-h-40 overflow-y-auto rounded-lg border border-ink-400 bg-surface">
                            @forelse ($leaveStaffResults as $member)
                                <button type="button" wire:key="pick-{{ $member->id }}" wire:click="selectLeaveStaff({{ $member->id }})"
                                    class="flex w-full min-h-11 items-center justify-between gap-2 border-b border-ink-200 px-3 py-2 text-start text-sm text-ink-800 last:border-b-0 hover:bg-white">
                                    <span class="truncate">{{ $member->name }}</span>
                                    @if ($member->position)
                                        <span class="shrink-0 text-xs text-ink-600">{{ $member->position }}</span>
                                    @endif
                                </button>
                            @empty
                                <p class="p-3 text-xs text-ink-600">No active staff match that search.</p>
                            @endforelse
                        </div>
                    @endif
                    <x-input-error :messages="$errors->get('leaveUserId')" class="mt-1" />
                </div>

                {{-- 2. Type: chips, not colours --}}
                <div>
                    <x-input-label value="Type" />
                    <div class="mt-1 flex flex-wrap gap-2">
                        @foreach ($leaveTypes as $type)
                            <button type="button" wire:key="type-{{ $type->value }}" wire:click="$set('leaveType', '{{ $type->value }}')"
                                @class([
                                    'min-h-11 rounded-lg border px-3 py-2 text-xs font-semibold transition sm:min-h-0',
                                    'border-leave bg-leave text-white' => $leaveType === $type->value,
                                    'border-ink-400 bg-white text-ink-700 hover:bg-ink-100' => $leaveType !== $type->value,
                                ])>
                                {{ $type->label() }}
                            </button>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('leaveType')" class="mt-1" />
                </div>

                {{-- 3 + 4. Dates --}}
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <x-input-label for="firstDay" value="First day away" />
                        <input type="date" wire:model.live="firstDay" id="firstDay"
                            class="mt-1 min-h-11 w-full rounded-lg border border-ink-400 bg-surface px-2.5 py-2 text-sm text-ink focus:border-leave focus:ring-0 sm:min-h-0">
                        <x-input-error :messages="$errors->get('firstDay')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="returnDate" value="Back at work on" />
                        <input type="date" wire:model.live="returnDate" id="returnDate"
                            class="mt-1 min-h-11 w-full rounded-lg border border-ink-400 bg-surface px-2.5 py-2 text-sm text-ink focus:border-leave focus:ring-0 sm:min-h-0">
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            @foreach ([1 => '+1 day', 7 => '+1 week', 14 => '+2 weeks'] as $days => $label)
                                <button type="button" wire:key="off-{{ $days }}" wire:click="setReturnOffset({{ $days }})"
                                    class="min-h-11 rounded-lg border border-ink-400 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-ink-700 transition hover:bg-ink-100 sm:min-h-0">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('returnDate')" class="mt-1" />
                    </div>
                </div>

                {{-- 5. Note --}}
                <div>
                    <x-input-label for="leaveNote" value="Note (optional)" />
                    <textarea wire:model="leaveNote" id="leaveNote" rows="2"
                        class="mt-1 block w-full rounded-lg border border-ink-400 bg-surface px-2.5 py-1.5 text-sm text-ink caret-leave focus:border-leave focus:ring-0"></textarea>
                    <x-input-error :messages="$errors->get('leaveNote')" class="mt-1" />
                </div>

                {{-- Live summary --}}
                @if ($summary)
                    <p class="rounded-lg border-s-4 border-leave bg-leave-tint px-3 py-2.5 text-sm font-semibold text-leave-dark">
                        {{ $summary['working'] }} working {{ Str::plural('day', $summary['working']) }}
                        ({{ $summary['calendar'] }} calendar) · back {{ $summary['back'] }}
                        <span class="block text-xs font-normal text-leave-mid">Last day away: {{ $summary['lastDay'] }}</span>
                    </p>
                @endif

                {{-- Live conflict report: existing assignments inside the window --}}
                @if (count($conflictReport) > 0)
                    <div class="rounded-lg border border-ink-400 bg-white">
                        @php
                            $conflictLine = ($leaveStaffMember?->name ?? 'This person')
                                .' is assigned to '.count($conflictReport).' '
                                .Str::plural('item', count($conflictReport))
                                .' during this leave. Reassign them if needed.';
                        @endphp
                        <p class="border-b border-ink-200 px-3 py-2.5 text-sm font-semibold text-ink">{{ $conflictLine }}</p>
                        <div class="max-h-48 overflow-y-auto">
                            @foreach ($conflictReport as $conflict)
                                <a wire:key="cr-{{ $loop->index }}"
                                    href="{{ $conflict['type'] === 'event' ? route('calendar') : ($conflict['type'] === 'gym' ? route('gym') : route('clinic')) }}"
                                    wire:navigate
                                    class="flex items-center justify-between gap-3 border-b border-ink-200 px-3 py-2 text-xs last:border-b-0 hover:bg-ink-100">
                                    <span class="min-w-0 truncate text-ink-800">{{ $conflict['label'] }}</span>
                                    <span class="shrink-0 font-semibold text-ink-600">{{ $conflict['starts_at']->format('D j M, g:ia') }}</span>
                                </a>
                            @endforeach
                        </div>
                        <p class="border-t border-ink-200 px-3 py-2 text-[11px] text-ink-600">
                            Nothing is removed automatically — saving this leave does not change any assignment.
                        </p>
                    </div>
                @endif

                <div class="flex justify-end gap-2 pt-1">
                    <x-secondary-button wire:click="$set('showLeaveForm', false)" type="button">Cancel</x-secondary-button>
                    <x-primary-button>{{ $editingLeaveId ? 'Save Changes' : 'Record Leave' }}</x-primary-button>
                </div>
            </form>
        </x-sheet>
    @endif

    {{-- ================= Staff detail sheet ================= --}}
    @if ($detailUser)
        <x-sheet :title="$detailUser->name"
            :badge="$detailUser->isRosterOnly() ? 'No login' : 'Login'"
            :badge-class="$detailUser->isRosterOnly() ? 'bg-leave text-white' : 'bg-ink text-white'"
            close="closeDetail" wide>

            <div class="flex gap-3 border-b border-ink-200 p-3.5">
                <span class="hps-label w-24 shrink-0 pt-0.5">Position</span>
                <span class="text-[13px] font-semibold text-ink">{{ $detailUser->position ?? '—' }}</span>
            </div>

            <div class="flex gap-3 border-b border-ink-200 p-3.5">
                <span class="hps-label w-24 shrink-0 pt-0.5">Email</span>
                <span class="min-w-0 break-words text-[13px] font-semibold text-ink">{{ $detailUser->email ?? 'None recorded' }}</span>
            </div>

            <div class="flex gap-3 border-b border-ink-200 p-3.5">
                <span class="hps-label w-24 shrink-0 pt-0.5">Status</span>
                <span class="text-[13px] font-semibold {{ $detailUser->is_active ? 'text-studio2' : 'text-accent-700' }}">
                    {{ $detailUser->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>

            @if ($detailTotals !== [])
                <div class="flex gap-3 border-b border-ink-200 p-3.5">
                    <span class="hps-label w-24 shrink-0 pt-0.5">{{ now()->year }} totals</span>
                    <span class="flex flex-wrap gap-1.5">
                        @foreach ($detailTotals as $typeValue => $days)
                            <span class="hps-chip">
                                {{ \App\Enums\LeaveType::from($typeValue)->shortLabel() }}: {{ $days }}d
                            </span>
                        @endforeach
                    </span>
                </div>
            @endif

            <div class="flex items-center justify-between gap-3 border-b border-ink-200 p-3.5">
                <span class="hps-label">Leave history</span>
                <button type="button" wire:click="$toggle('detailAllYears')"
                    class="text-[11px] font-semibold uppercase tracking-label text-accent-700 hover:text-accent">
                    {{ $detailAllYears ? 'This year only' : 'Show all years' }}
                </button>
            </div>

            @forelse ($detailLeaves as $leave)
                <div wire:key="hist-{{ $leave->id }}" class="flex items-start justify-between gap-3 border-b border-ink-200 p-3.5">
                    <span class="min-w-0">
                        <span class="block text-[13px] font-semibold {{ $leave->isCancelled() ? 'text-ink-500 line-through' : 'text-ink' }}">
                            {{ $leave->first_day->format('j M Y') }} – {{ $leave->lastDay()->format('j M Y') }}
                        </span>
                        <span class="block text-xs text-ink-600">
                            {{ $leave->type->label() }} · {{ $leave->workingDays() }} working
                            {{ Str::plural('day', $leave->workingDays()) }} · back {{ $leave->return_date->format('j M') }}
                        </span>
                        @if ($leave->note)
                            <span class="mt-1 block whitespace-pre-line text-xs text-ink-700">{{ $leave->note }}</span>
                        @endif
                        @if ($leave->isCancelled())
                            <span class="mt-1 inline-block rounded-sm border border-ink-400 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-label text-ink-600">Cancelled</span>
                        @endif
                    </span>

                    @unless ($leave->isCancelled())
                        <span class="flex shrink-0 flex-col items-end gap-1">
                            <button type="button" wire:click="openEditLeave({{ $leave->id }})"
                                class="py-1 text-[11px] font-semibold uppercase tracking-label text-ink-700 hover:text-ink">Edit</button>
                            <button type="button" wire:click="cancelLeave({{ $leave->id }})"
                                wire:confirm="Cancel this leave? The record is kept as history."
                                class="py-1 text-[11px] font-semibold uppercase tracking-label text-accent-700 hover:text-accent">Cancel</button>
                        </span>
                    @endunless
                </div>
            @empty
                <p class="p-6 text-center text-sm text-ink-600">No leave recorded{{ $detailAllYears ? '' : ' this year' }}.</p>
            @endforelse

            <x-slot name="actions">
                <x-dark-button wire:click="openLeaveForm({{ $detailUser->id }})" class="flex-1 justify-start">
                    Record leave for {{ Str::limit($detailUser->name, 18) }}
                </x-dark-button>
                @can('update', $detailUser)
                    <x-secondary-button wire:click="openStaffForm({{ $detailUser->id }})" type="button">Edit</x-secondary-button>
                @endcan
            </x-slot>
        </x-sheet>
    @endif

    {{-- ================= Add / edit roster staff ================= --}}
    @if ($showStaffForm)
        <x-sheet :title="$editingStaffId ? 'Edit staff' : 'Add staff'" close="$set('showStaffForm', false)">
            <form wire:submit="saveStaff" class="space-y-4 p-4">
                <div>
                    <x-input-label for="staffName" value="Name" />
                    <x-text-input wire:model="staffName" id="staffName" type="text" class="mt-1" />
                    <x-input-error :messages="$errors->get('staffName')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="staffPosition" value="Position (optional)" />
                    <x-text-input wire:model="staffPosition" id="staffPosition" type="text" class="mt-1"
                        placeholder="Physiotherapist, S&C Coach…" />
                    <x-input-error :messages="$errors->get('staffPosition')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="staffEmail" value="Email (optional)" />
                    <x-text-input wire:model="staffEmail" id="staffEmail" type="email" class="mt-1" />
                    <x-input-error :messages="$errors->get('staffEmail')" class="mt-1" />
                </div>

                @unless ($editingStaffId)
                    <p class="rounded-lg border-s-4 border-leave bg-leave-tint px-3 py-2 text-xs text-leave-dark">
                        Added to the roster with no login. They appear in every staff picker and in leave straight
                        away, and receive no notifications. The super admin can turn this into a login account later
                        with "Send invite" on the Users page.
                    </p>
                @endunless

                <div class="flex justify-end gap-2 pt-1">
                    <x-secondary-button wire:click="$set('showStaffForm', false)" type="button">Cancel</x-secondary-button>
                    <x-primary-button>{{ $editingStaffId ? 'Save Changes' : 'Add Staff' }}</x-primary-button>
                </div>
            </form>
        </x-sheet>
    @endif

    {{-- ================= Import list ================= --}}
    @if ($showImport)
        <x-sheet title="Import staff list" close="$set('showImport', false)" wide>
            <div class="space-y-4 p-4">

                @if ($importResult)
                    <div class="rounded-lg border-s-4 border-studio2 bg-studio2-tint px-3 py-2.5 text-sm font-semibold text-studio2-dark">
                        {{ $importResult['created'] }} staff added.
                        {{ $importResult['duplicates'] }} duplicate{{ $importResult['duplicates'] === 1 ? '' : 's' }}
                        and {{ $importResult['invalid'] }} invalid row{{ $importResult['invalid'] === 1 ? '' : 's' }} skipped.
                    </div>
                @endif

                <div class="hps-segment">
                    <button type="button" wire:click="setImportMode('paste')"
                        @class([
                            'flex-1 border-e border-ink-400 px-4 py-2 text-xs font-semibold uppercase tracking-label',
                            'bg-leave text-white' => $importMode === 'paste',
                            'bg-white text-ink-700' => $importMode !== 'paste',
                        ])>Paste a list</button>
                    <button type="button" wire:click="setImportMode('file')"
                        @class([
                            'flex-1 px-4 py-2 text-xs font-semibold uppercase tracking-label',
                            'bg-leave text-white' => $importMode === 'file',
                            'bg-white text-ink-700' => $importMode !== 'file',
                        ])>Upload CSV</button>
                </div>

                @if ($importMode === 'paste')
                    <div>
                        <x-input-label for="importText" value="One person per line: Name, Position, Email" />
                        <textarea wire:model="importText" id="importText" rows="6"
                            placeholder="Jane Kila, Physiotherapist, jane.kila@example.com&#10;Tom Bani, S&C Coach&#10;Mary Siaka"
                            class="mt-1 block w-full rounded-lg border border-ink-400 bg-surface px-2.5 py-1.5 font-mono text-xs text-ink caret-leave focus:border-leave focus:ring-0"></textarea>
                        <p class="mt-1 text-xs text-ink-600">Position and email are optional. Up to {{ \App\Livewire\Availability\StaffAvailabilityPage::IMPORT_MAX_ROWS }} rows.</p>
                        <x-input-error :messages="$errors->get('importText')" class="mt-1" />
                    </div>
                @else
                    <div>
                        <x-input-label for="importFile" value="CSV file (header: name,position,email)" />
                        <input type="file" wire:model="importFile" id="importFile" accept=".csv,text/csv"
                            class="mt-1 block w-full text-sm text-ink-800 file:mr-3 file:rounded-lg file:border file:border-ink-400 file:bg-white file:px-3 file:py-2 file:text-xs file:font-semibold file:text-ink-800">
                        <p class="mt-1 text-xs text-ink-600">
                            Up to 1MB and {{ \App\Livewire\Availability\StaffAvailabilityPage::IMPORT_MAX_ROWS }} rows.
                            <button type="button" wire:click="downloadTemplate" class="font-semibold text-accent-700 underline hover:text-accent">Download the template</button>.
                        </p>
                        <x-input-error :messages="$errors->get('importFile')" class="mt-1" />
                        <div wire:loading wire:target="importFile" class="mt-1 text-xs text-ink-600">Uploading…</div>
                    </div>
                @endif

                <x-dark-button wire:click="previewImport" type="button" class="w-full justify-center">Preview</x-dark-button>

                @if (count($importPreview) > 0)
                    <div class="hps-panel">
                        <div class="hps-panel-head">
                            <h3 class="text-sm font-bold tracking-tight text-ink">Preview</h3>
                            <span class="hps-label">
                                {{ collect($importPreview)->where('status', 'new')->count() }} new ·
                                {{ collect($importPreview)->where('status', 'duplicate')->count() }} duplicate ·
                                {{ collect($importPreview)->where('status', 'invalid')->count() }} invalid
                            </span>
                        </div>

                        <div class="max-h-64 overflow-y-auto">
                            <table class="min-w-full text-xs">
                                <tbody>
                                    @foreach ($importPreview as $row)
                                        <tr wire:key="prev-{{ $loop->index }}" class="border-b border-ink-200 last:border-b-0">
                                            <td class="px-3 py-2">
                                                <span class="font-semibold text-ink">{{ $row['name'] !== '' ? $row['name'] : '(no name)' }}</span>
                                                <span class="block text-ink-600">{{ $row['position'] ?? '—' }}{{ $row['email'] ? ' · '.$row['email'] : '' }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-end">
                                                <span @class([
                                                    'inline-block rounded-sm px-2 py-1 text-[10px] font-semibold uppercase tracking-label',
                                                    'bg-studio2 text-white' => $row['status'] === 'new',
                                                    'bg-ink-200 text-ink-800' => $row['status'] === 'duplicate',
                                                    'bg-accent text-white' => $row['status'] === 'invalid',
                                                ])>{{ $row['status'] }}</span>
                                                @if ($row['reason'])
                                                    <span class="block text-[10px] text-ink-600">{{ $row['reason'] }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button wire:click="$set('showImport', false)" type="button">Cancel</x-secondary-button>
                        <x-primary-button wire:click="confirmImport" type="button">
                            Add {{ collect($importPreview)->where('status', 'new')->count() }} staff
                        </x-primary-button>
                    </div>
                @endif
            </div>
        </x-sheet>
    @endif
</div>
