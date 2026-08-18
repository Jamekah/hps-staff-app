@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full border-l-4 border-accent bg-accent-100 py-3 ps-3 pe-4 text-start text-sm font-bold uppercase tracking-label text-accent-800 transition focus:outline-none'
            : 'block w-full border-l-4 border-transparent py-3 ps-3 pe-4 text-start text-sm font-semibold uppercase tracking-label text-ink-700 transition hover:border-ink-400 hover:bg-ink-100 hover:text-ink focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
