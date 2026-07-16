<div class="py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800">Announcements</h2>

            @can('create', App\Models\Announcement::class)
                <button wire:click="openCreate"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    + New Announcement
                </button>
            @endcan
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @forelse ($announcements as $announcement)
            <div wire:key="announcement-{{ $announcement->id }}" class="bg-white shadow-sm sm:rounded-lg p-5">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900">{{ $announcement->title }}</h3>

                    @can('update', $announcement)
                        <div class="flex gap-2 shrink-0">
                            <button wire:click="openEdit({{ $announcement->id }})"
                                class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                            <button wire:click="delete({{ $announcement->id }})"
                                wire:confirm="Delete this announcement?"
                                class="text-sm text-red-600 hover:text-red-800 font-medium">Delete</button>
                        </div>
                    @endcan
                </div>

                <p class="mt-2 text-sm text-gray-700 whitespace-pre-line">{{ $announcement->body }}</p>

                <p class="mt-3 text-xs text-gray-400">
                    {{ $announcement->creator->name }} · {{ $announcement->created_at->format('D j M Y, g:ia') }}
                </p>
            </div>
        @empty
            <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center text-gray-500 text-sm">
                No announcements yet.
            </div>
        @endforelse

        {{ $announcements->links() }}
    </div>

    {{-- Create / edit modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/50" wire:click="$set('showForm', false)"></div>

            <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit Announcement' : 'New Announcement' }}</h3>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <x-input-label for="ann-title" value="Title" />
                        <x-text-input wire:model="title" id="ann-title" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('title')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="ann-body" value="Message" />
                        <textarea wire:model="body" id="ann-body" rows="5"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button wire:click="$set('showForm', false)" type="button">Cancel</x-secondary-button>
                        <x-primary-button>{{ $editingId ? 'Save Changes' : 'Publish' }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
