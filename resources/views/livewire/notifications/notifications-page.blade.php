<div class="py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800">Notifications</h2>

            @if ($notifications->total() > 0)
                <button wire:click="markAllRead"
                    class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                    Mark all as read
                </button>
            @endif
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100">
            @forelse ($notifications as $notification)
                <div wire:key="notif-{{ $notification->id }}"
                    class="flex items-start gap-3 px-4 py-3 {{ $notification->read_at ? '' : 'bg-indigo-50/60' }}">
                    <button wire:click="open('{{ $notification->id }}')" class="flex-1 text-start min-w-0">
                        <p class="text-sm font-medium text-gray-800">{{ $notification->data['title'] ?? '' }}</p>
                        <p class="text-sm text-gray-600">{{ $notification->data['body'] ?? '' }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->format('D j M Y, g:ia') }} · {{ $notification->created_at->diffForHumans() }}</p>
                    </button>

                    @unless ($notification->read_at)
                        <button wire:click="markRead('{{ $notification->id }}')"
                            class="shrink-0 text-xs text-indigo-600 hover:text-indigo-800 font-medium mt-1">
                            Mark read
                        </button>
                    @endunless
                </div>
            @empty
                <p class="px-4 py-10 text-sm text-gray-500 text-center">No notifications yet.</p>
            @endforelse
        </div>

        {{ $notifications->links() }}
    </div>
</div>
