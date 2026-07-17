<div x-data="{ open: false }" class="relative" wire:poll.60s>
    <button @click="open = !open" type="button"
        class="relative p-2 text-gray-500 hover:text-gray-700 rounded-full focus:outline-none"
        aria-label="Notifications">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>

        @if ($unreadCount > 0)
            <span class="absolute top-0.5 end-0.5 inline-flex items-center justify-center min-w-4 h-4 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="open" x-cloak @click.outside="open = false"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        class="absolute end-0 mt-2 w-80 max-w-[calc(100vw-2rem)] bg-white rounded-lg shadow-lg border border-gray-100 z-50">
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100">
            <span class="text-sm font-semibold text-gray-800">Notifications</span>
            @if ($unreadCount > 0)
                <button wire:click="markAllRead" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                    Mark all read
                </button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto divide-y divide-gray-50">
            @forelse ($recent as $notification)
                <button wire:click="open('{{ $notification->id }}')" wire:key="bell-{{ $notification->id }}"
                    class="block w-full text-start px-4 py-2.5 hover:bg-gray-50 {{ $notification->read_at ? '' : 'bg-indigo-50/60' }}">
                    <div class="flex items-start gap-2">
                        @unless ($notification->read_at)
                            <span class="mt-1.5 w-2 h-2 rounded-full bg-indigo-500 shrink-0"></span>
                        @endunless
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $notification->data['title'] ?? '' }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $notification->data['body'] ?? '' }}</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </button>
            @empty
                <p class="px-4 py-6 text-sm text-gray-500 text-center">No notifications yet.</p>
            @endforelse
        </div>

        <a href="{{ route('notifications') }}" wire:navigate
            class="block text-center text-xs font-semibold text-indigo-600 hover:text-indigo-800 px-4 py-2.5 border-t border-gray-100">
            View all
        </a>
    </div>
</div>
