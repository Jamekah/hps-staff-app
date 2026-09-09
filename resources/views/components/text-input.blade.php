@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full rounded-lg border border-ink-400 bg-surface px-2.5 py-1.5 text-sm text-ink caret-accent placeholder:text-ink-500 hover:border-ink-500 focus:border-accent focus:ring-0 disabled:opacity-50']) }}>
