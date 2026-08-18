<div class="py-4 sm:py-6">
    <div class="mx-auto max-w-3xl space-y-4 px-3 sm:px-6 lg:px-8">

        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-extrabold tracking-tight text-ink sm:text-2xl">Announcements</h2>

            @can('create', App\Models\Announcement::class)
                <x-primary-button wire:click="openCreate" type="button" class="hidden sm:inline-flex">
                    + New Announcement
                </x-primary-button>
            @endcan
        </div>

        <x-flash />

        @forelse ($announcements as $announcement)
            <article wire:key="announcement-{{ $announcement->id }}" class="hps-panel p-5">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="text-lg font-extrabold leading-tight tracking-tight text-ink">{{ $announcement->title }}</h3>

                    @can('update', $announcement)
                        <div class="flex shrink-0 gap-3">
                            <button wire:click="openEdit({{ $announcement->id }})"
                                class="text-[11px] font-bold uppercase tracking-label text-ink-700 transition hover:text-ink">Edit</button>
                            <button wire:click="delete({{ $announcement->id }})"
                                wire:confirm="Delete this announcement?"
                                class="text-[11px] font-bold uppercase tracking-label text-accent-700 transition hover:text-accent">Delete</button>
                        </div>
                    @endcan
                </div>

                <div class="my-3 h-0.5 w-8 bg-accent"></div>

                <p class="whitespace-pre-line text-sm leading-relaxed text-ink-800">{{ $announcement->body }}</p>

                <p class="mt-4 text-[11px] font-semibold uppercase tracking-label text-ink-500">
                    {{ $announcement->creator->name }} · {{ $announcement->created_at->format('D j M Y, g:ia') }}
                </p>
            </article>
        @empty
            <div class="hps-panel p-10 text-center text-sm text-ink-600">
                No announcements yet.
            </div>
        @endforelse

        {{ $announcements->links() }}

        @can('create', App\Models\Announcement::class)
            <x-primary-button wire:click="openCreate" type="button" class="w-full justify-start py-4 sm:hidden">
                + New Announcement
            </x-primary-button>
        @endcan
    </div>

    {{-- Create / edit form --}}
    @if ($showForm)
        <x-sheet :title="$editingId ? 'Edit Announcement' : 'New Announcement'" close="$set('showForm', false)" wide>
            <form wire:submit="save" class="space-y-4 p-4">
                <div>
                    <x-input-label for="ann-title" value="Title" />
                    <x-text-input wire:model="title" id="ann-title" type="text" class="mt-1" />
                    <x-input-error :messages="$errors->get('title')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="ann-body" value="Message" />
                    <textarea wire:model="body" id="ann-body" rows="5"
                        class="mt-1 block w-full border border-ink-400 bg-surface px-2.5 py-1.5 text-sm text-ink caret-accent focus:border-accent focus:ring-0"></textarea>
                    <x-input-error :messages="$errors->get('body')" class="mt-1" />
                </div>

                @unless ($editingId)
                    <p class="border-s-4 border-ink bg-ink-100 px-3 py-2 text-xs text-ink-700">
                        Publishing notifies every active staff member immediately.
                    </p>
                @endunless

                <div class="flex justify-end gap-2 pt-1">
                    <x-secondary-button wire:click="$set('showForm', false)" type="button">Cancel</x-secondary-button>
                    <x-primary-button>{{ $editingId ? 'Save Changes' : 'Publish' }}</x-primary-button>
                </div>
            </form>
        </x-sheet>
    @endif
</div>
