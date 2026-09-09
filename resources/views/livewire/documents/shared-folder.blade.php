<div class="py-4 sm:py-6">
    <div class="mx-auto max-w-4xl space-y-4 px-3 sm:px-6 lg:px-8">

        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-bold tracking-tight text-ink sm:text-2xl">Shared Folder</h2>

            @can('create', App\Models\Document::class)
                <x-primary-button wire:click="openUpload" type="button" class="hidden sm:inline-flex">
                    + Upload File
                </x-primary-button>
            @endcan
        </div>

        <x-flash />

        <div class="hps-panel">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b-2 border-ink text-start">
                            <th class="hps-label px-4 py-3 text-start">File</th>
                            <th class="hps-label hidden px-4 py-3 text-start sm:table-cell">Size</th>
                            <th class="hps-label hidden px-4 py-3 text-start sm:table-cell">Uploaded</th>
                            <th class="hps-label px-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($documents as $document)
                            <tr wire:key="document-{{ $document->id }}" class="border-b border-ink-200 transition hover:bg-ink-100">
                                <td class="px-4 py-3">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        @switch($document->fileKind())
                                            @case('pdf')
                                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-accent text-[10px] font-semibold text-white">PDF</span>
                                                @break
                                            @case('word')
                                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-studio1 text-[10px] font-semibold text-white">DOC</span>
                                                @break
                                            @case('excel')
                                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-studio2 text-[10px] font-semibold text-white">XLS</span>
                                                @break
                                            @default
                                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-ink-800 text-[10px] font-semibold text-white">FILE</span>
                                        @endswitch
                                        <div class="min-w-0">
                                            <div class="truncate font-semibold text-ink">{{ $document->title }}</div>
                                            <div class="truncate text-xs text-ink-600">
                                                {{ $document->original_filename }}
                                                <span class="sm:hidden">· {{ $document->humanSize() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden whitespace-nowrap px-4 py-3 text-ink-700 sm:table-cell">{{ $document->humanSize() }}</td>
                                <td class="hidden whitespace-nowrap px-4 py-3 text-ink-700 sm:table-cell">{{ $document->created_at->format('j M Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end">
                                    <a href="{{ route('documents.download', $document) }}"
                                        class="inline-flex items-center py-3 text-[11px] font-semibold uppercase tracking-label text-accent-700 transition hover:text-accent sm:py-0">Download</a>
                                    @can('delete', $document)
                                        <button wire:click="delete({{ $document->id }})"
                                            wire:confirm="Delete “{{ $document->title }}”? This cannot be undone."
                                            class="ms-3 inline-flex items-center py-3 text-[11px] font-semibold uppercase tracking-label text-ink-700 transition hover:text-ink sm:py-0">Delete</button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-sm text-ink-600">No files uploaded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4">
                {{ $documents->links() }}
            </div>
        </div>

        @can('create', App\Models\Document::class)
            <x-primary-button wire:click="openUpload" type="button" class="w-full justify-start py-4 sm:hidden">
                + Upload File
            </x-primary-button>
        @endcan
    </div>

    {{-- Upload form --}}
    @if ($showForm)
        <x-sheet title="Upload File" close="$set('showForm', false)">
            <form wire:submit="save" class="space-y-4 p-4">
                <div>
                    <x-input-label for="doc-title" value="Title" />
                    <x-text-input wire:model="title" id="doc-title" type="text" class="mt-1" />
                    <x-input-error :messages="$errors->get('title')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="doc-file" value="File (PDF, Word or Excel — max 20MB)" />
                    <input type="file" wire:model="file" id="doc-file" accept=".pdf,.doc,.docx,.xls,.xlsx"
                        class="mt-1 block w-full rounded-lg border border-ink-400 bg-surface p-2 text-sm text-ink-700 file:me-3 file:border-0 file:bg-ink file:px-3 file:py-1.5 file:text-xs file:font-semibold file:uppercase file:tracking-label file:text-ink-100 hover:file:bg-ink-900">
                    <div wire:loading wire:target="file" class="mt-1.5 text-xs font-semibold text-ink-600">Uploading…</div>
                    <x-input-error :messages="$errors->get('file')" class="mt-1" />
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <x-secondary-button wire:click="$set('showForm', false)" type="button">Cancel</x-secondary-button>
                    <x-primary-button wire:loading.attr="disabled" wire:target="file">Upload</x-primary-button>
                </div>
            </form>
        </x-sheet>
    @endif
</div>
