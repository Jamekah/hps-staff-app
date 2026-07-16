<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'original_filename',
        'storage_path',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size_bytes, precision: 1);
    }

    /**
     * Broad file category for picking a type icon: pdf, word, excel, or file.
     */
    public function fileKind(): string
    {
        return match (true) {
            $this->mime_type === 'application/pdf' => 'pdf',
            str_contains($this->mime_type, 'word') || str_contains($this->mime_type, 'msword') => 'word',
            str_contains($this->mime_type, 'sheet') || str_contains($this->mime_type, 'excel') => 'excel',
            default => 'file',
        };
    }
}
