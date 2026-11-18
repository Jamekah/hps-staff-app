@props([
    'cell',
    'caution' => 0.8,
    'compact' => false,
])

{{--
    One allowance figure: used / limit, a slim bar, and what is left.

    Colour is never the only signal — the trailing text differs in all three
    states ("6 left", "2 left", "+3 over"), so the over-limit red is readable
    as over-limit even beside the brand-red buttons.
--}}
@php
    $limit = $cell['limit'] ?? null;
    $used = $cell['used'] ?? 0;
    $over = $cell['over'] ?? 0;
    $remaining = $cell['remaining'] ?? null;

    $ratio = $limit > 0 ? $used / $limit : 0;
    $isOver = $over > 0;
    $isCaution = ! $isOver && $ratio >= $caution;

    $width = $limit > 0 ? min(100, round($ratio * 100)) : 0;
@endphp

@if ($limit === null)
    <span class="text-sm {{ $used > 0 ? 'font-semibold text-ink' : 'text-ink-500' }}">
        {{ $used > 0 ? $used.'d' : '—' }}
    </span>
@else
    <span class="block">
        <span class="flex items-baseline gap-1.5">
            <span @class([
                'text-sm font-bold',
                'text-accent-800' => $isOver,
                'text-leave-dark' => $isCaution,
                'text-ink' => ! $isOver && ! $isCaution,
            ])>{{ $used }}</span>
            <span class="text-xs text-ink-500">/ {{ $limit }}</span>
        </span>

        <span class="mt-1 block h-1.5 w-full max-w-[7rem] overflow-hidden rounded-full bg-ink-200">
            <span @class([
                'block h-full rounded-full',
                'bg-accent-700' => $isOver,
                'bg-leave-dark' => $isCaution,
                'bg-leave' => ! $isOver && ! $isCaution,
            ]) style="width: {{ $width }}%"></span>
        </span>

        <span @class([
            'mt-1 block text-[11px]',
            'font-bold text-accent-800' => $isOver,
            'font-bold text-leave-dark' => $isCaution,
            'text-ink-600' => ! $isOver && ! $isCaution,
        ])>
            @if ($isOver)
                +{{ $over }} over
            @else
                {{ $remaining }} left
            @endif
        </span>

        @unless ($compact)
            <span class="block text-[10px] text-ink-500" title="{{ $cell['taken'] }} taken, {{ $cell['booked'] }} booked">
                {{ $cell['taken'] }} taken · {{ $cell['booked'] }} booked
            </span>
        @endunless
    </span>
@endif
