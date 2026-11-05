<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * "Preview" for the attachment viewer modal: the same file the download route
 * serves, but inline, and only for types a browser can display safely (PDF and
 * images). Anything else (Word, Excel) is sent as a normal download instead.
 * Each controller authorizes the request exactly as its download route does
 * before calling this.
 */
trait ServesAttachmentPreview
{
    private function inlinePreview(string $disk, string $path, string $name, ?string $mime)
    {
        abort_unless(Storage::disk($disk)->exists($path), 404, 'File not found on storage.');

        $previewable = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (! in_array(strtolower((string) $mime), $previewable, true)) {
            return Storage::disk($disk)->download($path, $name);
        }

        return Storage::disk($disk)->response($path, $name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
