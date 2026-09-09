@props(['status'])

@php
    use App\Enums\AppointmentStatus;

    $classes = match ($status) {
        AppointmentStatus::Scheduled => 'bg-clinic text-white',
        AppointmentStatus::Completed => 'bg-studio2 text-white',
        AppointmentStatus::NoShow => 'bg-accent text-white',
        AppointmentStatus::Cancelled => 'bg-ink-300 text-ink-800 line-through',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex rounded-sm px-2 py-0.5 text-[10px] font-semibold uppercase tracking-label {$classes}"]) }}>
    {{ $status->label() }}
</span>
