<?php

namespace App\Livewire\Documents;

use App\Models\Document;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class SharedFolder extends Component
{
    use AuthorizesRequests, WithFileUploads, WithPagination;

    /** MIME types accepted: PDF, Word (.doc/.docx), Excel (.xls/.xlsx). */
    public const ALLOWED_MIMETYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public bool $showForm = false;

    public string $title = '';

    public $file = null;

    public function openUpload(): void
    {
        $this->authorize('create', Document::class);

        $this->reset(['title', 'file']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('create', Document::class);

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'file' => [
                'required',
                'file',
                'max:20480', // 20MB in kilobytes.
                'mimetypes:'.implode(',', self::ALLOWED_MIMETYPES),
            ],
        ], [
            'file.mimetypes' => 'Only PDF, Word (.doc/.docx) and Excel (.xls/.xlsx) files are allowed.',
            'file.max' => 'The file may not be larger than 20MB.',
        ]);

        // hashName() gives a random, non-guessable filename. The disk must be
        // explicit: Livewire's storeAs otherwise defaults to its temporary
        // upload disk, not the application disk.
        $path = $this->file->storeAs('documents', $this->file->hashName(), [
            'disk' => config('filesystems.default'),
        ]);

        Document::create([
            'title' => $this->title,
            'original_filename' => $this->file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $this->file->getMimeType(),
            'size_bytes' => $this->file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        $this->showForm = false;
        $this->reset(['title', 'file']);
        session()->flash('status', 'File uploaded.');
    }

    public function delete(int $documentId): void
    {
        $document = Document::findOrFail($documentId);
        $this->authorize('delete', $document);

        Storage::delete($document->storage_path);
        $document->delete();

        session()->flash('status', 'File deleted.');
    }

    public function render()
    {
        return view('livewire.documents.shared-folder', [
            'documents' => Document::with('uploader')->latest()->paginate(15),
        ]);
    }
}
