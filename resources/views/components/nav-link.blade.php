@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center border-b-2 border-accent pb-1 text-sm font-semibold text-ink transition focus:outline-none'
            : 'inline-flex items-center border-b-2 border-transparent pb-1 text-sm font-medium text-ink-700 transition hover:text-ink hover:border-ink-400 focus:outline-none focus:text-ink';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
