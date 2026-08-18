@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'border-l-4 border-studio2 bg-studio2-tint px-3 py-2 text-sm font-semibold text-studio2-dark']) }}>
        {{ $status }}
    </div>
@endif
