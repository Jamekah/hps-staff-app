<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DocumentDownloadController extends Controller
{
    /**
     * Serve a document to any authenticated user. On S3-compatible storage
     * (Laravel Cloud) this redirects to a short-lived signed URL — files are
     * never publicly accessible. On the local disk (dev) it streams the file.
     */
    public function __invoke(Document $document)
    {
        Gate::authorize('view', $document);

        $diskName = config('filesystems.default');
        $disk = Storage::disk($diskName);

        if (config("filesystems.disks.{$diskName}.driver") === 's3') {
            return redirect()->away($disk->temporaryUrl(
                $document->storage_path,
                now()->addMinutes(5),
                [
                    'ResponseContentDisposition' => 'attachment; filename="'.addslashes($document->original_filename).'"',
                ]
            ));
        }

        return $disk->download($document->storage_path, $document->original_filename);
    }
}
