@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-xs font-semibold text-ink-700']) }}>
    {{ $value ?? $slot }}
</label>
