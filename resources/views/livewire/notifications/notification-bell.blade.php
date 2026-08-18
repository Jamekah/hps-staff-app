<div x-data="{ open: false }" class="relative" wire:poll.60s>
    <button @click="open = !open" type="button"
        class="relative p-2 text-ink-800 transition hover:text-accent focus:outline-none"
        aria-label="Notifications">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.7 21a2 2 0 0 1-3.4 0"/>
        </svg>

        @if ($unreadCount > 0)
            <span class="absolute -top-0.5 -end-0.5 inline-flex h-4 min-w-4 items-center justify-center bg-accent px-1 text-[10px] font-extrabold text-white">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="open" x-cloak @click.outside="open = false"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        class="absolute end-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] border border-ink bg-white shadow-lg">
        <div class="flex items-center justify-between border-b-2 border-ink px-4 py-3">
            <span class="hps-label text-ink">Notifications</span>
            @if ($unreadCount > 0)
                <button wire:click="markAllRead" class="text-[11px] font-bold uppercase tracking-label text-accent-700 transition hover:text-accent">
                    Mark all read
                </button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse ($recent as $notification)
                <button wire:click="open('{{ $notification->id }}')" wire:key="bell-{{ $notification->id }}"
                    class="block w-full border-t border-ink-200 px-4 py-3 text-start transition first:border-t-0 hover:bg-ink-100 {{ $notification->read_at ? '' : 'bg-accent-100/60' }}">
                    <div class="flex items-start gap-2.5">
                        @unless ($notification->read_at)
                            <span class="mt-1.5 h-2 w-2 shrink-0 bg-accent"></span>
                        @endunless
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-ink">{{ $notification->data['title'] ?? '' }}</p>
                            <p class="truncate text-xs text-ink-700">{{ $notification->data['body'] ?? '' }}</p>
                            <p class="mt-0.5 text-[11px] text-ink-500">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </button>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-600">No notifications yet.</p>
            @endforelse
        </div>

        <a href="{{ route('notifications') }}" wire:navigate
            class="block border-t-2 border-ink px-4 py-3 text-center text-[11px] font-extrabold uppercase tracking-label text-accent-700 transition hover:bg-ink-100">
            View all
        </a>
    </div>
</div>
