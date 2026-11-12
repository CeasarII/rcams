@props(['record', 'prefix'])

{{-- Record-level documents (Treasury's proof of payment). $prefix is the route-name prefix,
     e.g. "liquidations.attachments"; every route takes [record, attachment]. --}}
@php($attachments = $record->attachments()->with('uploader')->orderBy('uploaded_at')->get())

@if ($attachments->isNotEmpty() || auth()->user()->can('attachDisbursement', $record))
    <div class="card mt-4">
        <div class="card-header">Attachments</div>
        <ul class="list-group list-group-flush">
            @forelse ($attachments as $attachment)
                <li class="list-group-item small d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <x-attachment-link :href="route($prefix.'.download', [$record, $attachment])" :preview="route($prefix.'.preview', [$record, $attachment])" :name="$attachment->original_file_name" :mime="$attachment->mime_type" />
                        <div class="text-muted">
                            {{ $attachment->humanSize() }} · {{ $attachment->uploader->full_name }} · {{ $attachment->uploaded_at->format('M d, Y') }}
                        </div>
                    </div>
                    @can('removeAttachment', [$record, $attachment])
                        <form method="POST" action="{{ route($prefix.'.destroy', [$record, $attachment]) }}"
                              onsubmit="return confirm('Remove this attachment?');" class="mb-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                        </form>
                    @endcan
                </li>
            @empty
                <li class="list-group-item small text-muted">No attachments yet.</li>
            @endforelse
        </ul>

        @can('attachDisbursement', $record)
            <div class="card-body border-top">
                <form method="POST" action="{{ route($prefix.'.store', $record) }}" enctype="multipart/form-data">
                    @csrf
                    <label class="form-label small">Add files <span class="text-muted">(PDF, JPG, PNG, Word, Excel · up to 5 files, 5 MB each)</span></label>
                    <input type="file" name="files[]" class="form-control mb-2" multiple required
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    <button type="submit" class="btn btn-outline-primary btn-sm">Upload</button>
                </form>
            </div>
        @endcan
    </div>
@endif
