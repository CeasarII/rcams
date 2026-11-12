<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesRecordAttachments;
use App\Models\Attachment;
use App\Models\RevolvingFundExpense;
use Illuminate\Http\Request;

/** Treasury's supporting documents on a revolving fund expenses at its disbursement step. */
class RevolvingFundExpenseAttachmentController extends Controller
{
    use HandlesRecordAttachments;

    public function store(Request $request, RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('attachDisbursement', $revolvingFundExpense);

        return $this->storeRecordAttachments($request, $revolvingFundExpense, 'revolving-fund-expenses');
    }

    public function download(RevolvingFundExpense $revolvingFundExpense, Attachment $attachment)
    {
        $this->authorize('view', $revolvingFundExpense);
        $this->assertAttachmentBelongsTo($revolvingFundExpense, $attachment);

        return $this->downloadRecordAttachment($attachment);
    }

    public function preview(RevolvingFundExpense $revolvingFundExpense, Attachment $attachment)
    {
        $this->authorize('view', $revolvingFundExpense);
        $this->assertAttachmentBelongsTo($revolvingFundExpense, $attachment);

        return $this->previewRecordAttachment($attachment);
    }

    public function destroy(RevolvingFundExpense $revolvingFundExpense, Attachment $attachment)
    {
        $this->assertAttachmentBelongsTo($revolvingFundExpense, $attachment);
        $this->authorize('removeAttachment', [$revolvingFundExpense, $attachment]);

        return $this->destroyRecordAttachment($attachment);
    }
}
