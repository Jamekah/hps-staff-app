<div class="py-4 sm:py-6">
    <div class="mx-auto max-w-3xl space-y-4 px-3 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-extrabold tracking-tight text-ink sm:text-2xl">Notifications</h2>

            @if ($notifications->total() > 0)
                <button wire:click="markAllRead"
                    class="text-[11px] font-bold uppercase tracking-label text-accent-700 transition hover:text-accent">
                    Mark all as read
                </button>
            @endif
        </div>

        <div class="hps-panel">
            @forelse ($notifications as $notification)
                <div wire:key="notif-{{ $notification->id }}"
                    class="hps-row flex items-start gap-3 p-4 first:border-t-0 {{ $notification->read_at ? '' : 'bg-accent-100/60' }}">
                    @unless ($notification->read_at)
                        <span class="mt-1.5 h-2 w-2 shrink-0 bg-accent"></span>
                    @endunless

                    <button wire:click="open('{{ $notification->id }}')" class="min-w-0 flex-1 text-start">
                        <p class="text-sm font-bold text-ink">{{ $notification->data['title'] ?? '' }}</p>
                        <p class="mt-0.5 text-sm text-ink-800">{{ $notification->data['body'] ?? '' }}</p>
                        <p class="mt-1.5 text-[11px] font-semibold uppercase tracking-label text-ink-500">
                            {{ $notification->created_at->format('D j M Y, g:ia') }} · {{ $notification->created_at->diffForHumans() }}
                        </p>
                    </button>

                    @unless ($notification->read_at)
                        <button wire:click="markRead('{{ $notification->id }}')"
                            class="mt-0.5 shrink-0 text-[11px] font-bold uppercase tracking-label text-ink-700 transition hover:text-ink">
                            Mark read
                        </button>
                    @endunless
                </div>
            @empty
                <p class="p-10 text-center text-sm text-ink-600">No notifications yet.</p>
            @endforelse
        </div>

        {{ $notifications->links() }}
    </div>
</div>
