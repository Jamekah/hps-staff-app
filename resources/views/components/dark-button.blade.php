{{-- Ink-filled action, used where a design calls for a heavy non-destructive
     primary that isn't the brand red (e.g. "Edit event" in detail sheets). --}}
<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-1.5 rounded-lg bg-ink px-4 py-2.5 text-xs font-semibold uppercase tracking-label text-ink-100 transition hover:bg-ink-900 active:bg-black focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:opacity-45']) }}>
    {{ $slot }}
</button>
