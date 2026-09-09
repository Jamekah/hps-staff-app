<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-1.5 rounded-lg border border-accent bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-label text-accent-700 transition hover:bg-accent-100 active:bg-accent-200 focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:opacity-45']) }}>
    {{ $slot }}
</button>
