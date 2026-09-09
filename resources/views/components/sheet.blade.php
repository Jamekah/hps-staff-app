@props([
    'title',
    'badge' => null,
    'badgeClass' => 'bg-ink text-white',
    'close' => null,
    'wide' => false,
])

{{--
    Detail / form sheet. Backdrop dismisses via the same action as the close
    button, so `close` must be a Livewire action string (e.g. "$set('x', false)").
    `actions` slot renders the ink-ruled footer used for admin controls.
--}}
{{-- Padding keeps the sheet floating as a rounded card rather than sitting
     flush to the screen edge, which the 12px corners need to read properly. --}}
<div class="fixed inset-0 z-50 flex items-end justify-center p-3 sm:items-center sm:p-4" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-ink-900/60" @if ($close) wire:click="{{ $close }}" @endif></div>

    <div class="relative flex max-h-[92vh] w-full flex-col overflow-hidden rounded-xl border border-ink bg-white shadow-lg {{ $wide ? 'sm:max-w-2xl' : 'sm:max-w-md' }}">
        <div class="flex shrink-0 items-start justify-between gap-3 border-b-2 border-ink p-4">
            <div class="min-w-0">
                @if ($badge)
                    <div class="mb-2 inline-block rounded-sm px-2 py-1 text-[10px] font-semibold uppercase tracking-label {{ $badgeClass }}">
                        {{ $badge }}
                    </div>
                @endif
                <h2 class="text-xl font-bold leading-tight tracking-tight text-ink">{{ $title }}</h2>
            </div>

            @if ($close)
                <button type="button" wire:click="{{ $close }}" class="-m-1 shrink-0 p-1 text-ink-800 transition hover:text-accent" aria-label="Close">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                </button>
            @endif
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            {{ $slot }}
        </div>

        @isset($actions)
            <div class="flex shrink-0 gap-2 border-t-2 border-ink bg-ink-100 p-3">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>
