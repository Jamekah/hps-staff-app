<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-1.5 rounded-lg border border-ink-400 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-label text-ink-800 transition hover:bg-ink-100 active:bg-ink-200 focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:opacity-45']) }}>
    {{ $slot }}
</button>
