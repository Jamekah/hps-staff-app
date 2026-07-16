<div class="py-6">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800">Shared Folder</h2>

            @can('create', App\Models\Document::class)
                <button wire:click="openUpload"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    + Upload File
                </button>
            @endcan
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">File</th>
                            <th class="px-4 py-3 hidden sm:table-cell">Size</th>
                            <th class="px-4 py-3 hidden sm:table-cell">Uploaded</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($documents as $document)
                            <tr wire:key="document-{{ $document->id }}">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        @switch($document->fileKind())
                                            @case('pdf')
                                                <span class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded bg-red-100 text-red-700 text-[10px] font-bold">PDF</span>
                                                @break
                                            @case('word')
                                                <span class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded bg-blue-100 text-blue-700 text-[10px] font-bold">DOC</span>
                                                @break
                                            @case('excel')
                                                <span class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded bg-green-100 text-green-700 text-[10px] font-bold">XLS</span>
                                                @break
                                            @default
                                                <span class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded bg-gray-100 text-gray-600 text-[10px] font-bold">FILE</span>
                                        @endswitch
                                        <div class="min-w-0">
                                            <div class="font-medium text-gray-900 truncate">{{ $document->title }}</div>
                                            <div class="text-xs text-gray-500 truncate">
                                                {{ $document->original_filename }}
                                                <span class="sm:hidden">· {{ $document->humanSize() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-600 hidden sm:table-cell whitespace-nowrap">{{ $document->humanSize() }}</td>
                                <td class="px-4 py-3 text-gray-600 hidden sm:table-cell whitespace-nowrap">{{ $document->created_at->format('j M Y') }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                                    <a href="{{ route('documents.download', $document) }}"
                                        class="text-indigo-600 hover:text-indigo-800 font-medium">Download</a>
                                    @can('delete', $document)
                                        <button wire:click="delete({{ $document->id }})"
                                            wire:confirm="Delete “{{ $document->title }}”? This cannot be undone."
                                            class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">No files uploaded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4">
                {{ $documents->links() }}
            </div>
        </div>
    </div>

    {{-- Upload modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/50" wire:click="$set('showForm', false)"></div>

            <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">Upload File</h3>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <x-input-label for="doc-title" value="Title" />
                        <x-text-input wire:model="title" id="doc-title" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('title')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="doc-file" value="File (PDF, Word or Excel — max 20MB)" />
                        <input type="file" wire:model="file" id="doc-file" accept=".pdf,.doc,.docx,.xls,.xlsx"
                            class="mt-1 block w-full text-sm text-gray-600 file:me-3 file:px-3 file:py-1.5 file:rounded-md file:border-0 file:bg-gray-800 file:text-white file:text-xs file:font-semibold hover:file:bg-gray-700">
                        <div wire:loading wire:target="file" class="mt-1 text-xs text-gray-500">Uploading…</div>
                        <x-input-error :messages="$errors->get('file')" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button wire:click="$set('showForm', false)" type="button">Cancel</x-secondary-button>
                        <x-primary-button wire:loading.attr="disabled" wire:target="file">Upload</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
