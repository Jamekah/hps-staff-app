@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'space-y-1 text-xs font-semibold text-accent-700']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
