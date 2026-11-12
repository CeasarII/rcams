<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\CashAdvanceLiquidation;
use App\Models\LiquidationStatus;
use App\Models\User;
use App\Support\LiquidationAccess;

class CashAdvanceLiquidationPolicy
{
    public function view(User $user, CashAdvanceLiquidation $liquidation): bool
    {
        return LiquidationAccess::canViewLiquidation($user, $liquidation);
    }

    /**
     * Items, header, attachments and submit: only the Requestor who prepared
     * it, and only while it is Draft or sent back (Edit / Revise liquidation).
     */
    public function update(User $user, CashAdvanceLiquidation $liquidation): bool
    {
        return (int) $liquidation->prepared_by === (int) $user->id
            && $this->in($liquidation, LiquidationStatus::EDITABLE);
    }

    /** OE: "Liquidation okay?" — scoped to the cash advance's vessel/department. */
    public function validateAsOe(User $user, CashAdvanceLiquidation $liquidation): bool
    {
        return $user->hasRole('OE')
            && (int) $liquidation->prepared_by !== (int) $user->id // segregation of duties
            && $this->oeIsInScope($user, $liquidation)
            && $this->in($liquidation, [LiquidationStatus::FOR_OE_VALIDATION]);
    }

    /** Staff: "Verification okay?" */
    public function verifyAsStaff(User $user, CashAdvanceLiquidation $liquidation): bool
    {
        return $user->hasRole('ACCT_STAFF')
            && (int) $liquidation->prepared_by !== (int) $user->id
            && $this->in($liquidation, [LiquidationStatus::FOR_STAFF_VERIFICATION]);
    }

    public function prepareApv(User $user, CashAdvanceLiquidation $liquidation): bool
    {
        return $user->hasRole('ACCT_STAFF')
            && (int) $liquidation->prepared_by !== (int) $user->id
            && $this->in($liquidation, [LiquidationStatus::FOR_APV_PREPARATION]);
    }

    /** Head: "Review okay?" on the APV. */
    public function reviewApv(User $user, CashAdvanceLiquidation $liquidation): bool
    {
        return $user->hasRole('ACCT_HEAD')
            && (int) $liquidation->prepared_by !== (int) $user->id
            && $this->in($liquidation, [LiquidationStatus::FOR_APV_REVIEW]);
    }

    /** Head: process the liquidation and tag it closed (nothing payable to the requestor). */
    public function close(User $user, CashAdvanceLiquidation $liquidation): bool
    {
        return $user->hasRole('ACCT_HEAD')
            && (int) $liquidation->prepared_by !== (int) $user->id
            && $this->in($liquidation, [LiquidationStatus::FOR_HEAD_CLOSURE]);
    }

    public function reimburse(User $user, CashAdvanceLiquidation $liquidation): bool
    {
        return $user->hasRole('TREASURY')
            && (int) $liquidation->prepared_by !== (int) $user->id
            && $this->in($liquidation, [LiquidationStatus::FOR_REIMBURSEMENT]);
    }

    /**
     * Treasury adds supporting documents (e.g. proof of payment) at the
     * reimbursement step — a liquidation's equivalent of "For Disbursement".
     */
    public function attachDisbursement(User $user, CashAdvanceLiquidation $liquidation): bool
    {
        return $this->reimburse($user, $liquidation);
    }

    /** Treasury may remove only the files they uploaded, and only at that step. */
    public function removeAttachment(User $user, CashAdvanceLiquidation $liquidation, Attachment $attachment): bool
    {
        return $this->attachDisbursement($user, $liquidation)
            && (int) $attachment->uploaded_by === (int) $user->id;
    }

    /** Tag/untag items as Questionable — at the OE's and Staff's "okay?" decisions. */
    public function tagQuestionable(User $user, CashAdvanceLiquidation $liquidation): bool
    {
        return $this->validateAsOe($user, $liquidation) || $this->verifyAsStaff($user, $liquidation);
    }

    private function in(CashAdvanceLiquidation $liquidation, array $codes): bool
    {
        return in_array($liquidation->status->code, $codes, true);
    }

    /** Same rule as FundRequestPolicy / FundRequestWorkflowService::assertOeScope(). */
    private function oeIsInScope(User $user, CashAdvanceLiquidation $liquidation): bool
    {
        $request = $liquidation->fundRequest;

        return $request->vessel_id !== null
            ? $user->vessels()->where('vessels.id', $request->vessel_id)->exists()
            : $user->departments()->where('departments.id', $request->department_id)->exists();
    }
}
