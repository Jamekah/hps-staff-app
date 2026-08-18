<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-1.5 bg-accent px-4 py-2.5 text-xs font-extrabold uppercase tracking-label text-white transition hover:bg-accent-600 active:bg-accent-700 focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:opacity-45']) }}>
    {{ $slot }}
</button>
