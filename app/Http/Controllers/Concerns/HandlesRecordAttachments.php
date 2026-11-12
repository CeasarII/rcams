<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Record-level supporting documents (the Treasury's proof of payment at the
 * disbursement step) for a Liquidation or a Revolving Fund Expense. Files go
 * to the PRIVATE "local" disk and are served only through the download and
 * preview routes. The calling controller authorizes first, then delegates here.
 */
trait HandlesRecordAttachments
{
    use ServesAttachmentPreview;

    private const ATTACHMENT_DISK = 'local';

    private function storeRecordAttachments(Request $request, Model $record, string $folder)
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'], // 5 MB each
        ], [
            'files.*.max' => 'Each file must be 5 MB or smaller.',
            'files.*.mimes' => 'Allowed types: PDF, JPG, PNG, Word, Excel.',
            'files.*.uploaded' => 'Upload failed — the file may exceed the server upload limit (php.ini upload_max_filesize).',
        ]);

        foreach ($request->file('files') as $file) {
            // Name on disk is generated; the client's file name is kept only as a label.
            $storedName = Str::uuid().'.'.$file->extension();
            $path = $file->storeAs("attachments/{$folder}/{$record->getKey()}", $storedName, self::ATTACHMENT_DISK);

            $record->attachments()->create([
                'original_file_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'stored_file_name' => $storedName,
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'file_size' => $file->getSize(),
                'uploaded_by' => Auth::id(),
                'uploaded_at' => now(),
            ]);
        }

        $count = count($request->file('files'));

        return back()->with('status', $count === 1 ? 'File attached.' : "{$count} files attached.");
    }

    private function downloadRecordAttachment(Attachment $attachment)
    {
        abort_unless(Storage::disk(self::ATTACHMENT_DISK)->exists($attachment->file_path), 404, 'File not found on storage.');

        return Storage::disk(self::ATTACHMENT_DISK)->download($attachment->file_path, $attachment->original_file_name);
    }

    private function previewRecordAttachment(Attachment $attachment)
    {
        return $this->inlinePreview(self::ATTACHMENT_DISK, $attachment->file_path, $attachment->original_file_name, $attachment->mime_type);
    }

    private function destroyRecordAttachment(Attachment $attachment)
    {
        Storage::disk(self::ATTACHMENT_DISK)->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('status', 'Attachment removed.');
    }

    /** Stops /liquidations/1/attachments/99 from reaching another record's file. */
    private function assertAttachmentBelongsTo(Model $record, Attachment $attachment): void
    {
        abort_unless(
            $attachment->attachable_type === $record->getMorphClass()
                && (int) $attachment->attachable_id === (int) $record->getKey(),
            404
        );
    }
}
