<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesRecordAttachments;
use App\Models\Attachment;
use App\Models\CashAdvanceLiquidation;
use Illuminate\Http\Request;

/** Treasury's supporting documents on a liquidations at its disbursement step. */
class LiquidationAttachmentController extends Controller
{
    use HandlesRecordAttachments;

    public function store(Request $request, CashAdvanceLiquidation $liquidation)
    {
        $this->authorize('attachDisbursement', $liquidation);

        return $this->storeRecordAttachments($request, $liquidation, 'liquidations');
    }

    public function download(CashAdvanceLiquidation $liquidation, Attachment $attachment)
    {
        $this->authorize('view', $liquidation);
        $this->assertAttachmentBelongsTo($liquidation, $attachment);

        return $this->downloadRecordAttachment($attachment);
    }

    public function preview(CashAdvanceLiquidation $liquidation, Attachment $attachment)
    {
        $this->authorize('view', $liquidation);
        $this->assertAttachmentBelongsTo($liquidation, $attachment);

        return $this->previewRecordAttachment($attachment);
    }

    public function destroy(CashAdvanceLiquidation $liquidation, Attachment $attachment)
    {
        $this->assertAttachmentBelongsTo($liquidation, $attachment);
        $this->authorize('removeAttachment', [$liquidation, $attachment]);

        return $this->destroyRecordAttachment($attachment);
    }
}
