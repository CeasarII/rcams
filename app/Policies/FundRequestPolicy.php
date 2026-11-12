<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\FundRequest;
use App\Models\RequestStatus;
use App\Models\User;

class FundRequestPolicy
{
    /** Requestor sees only their own; every other role sees all (Phase 1 §8 Q7 assumption). */
    public function view(User $user, FundRequest $fundRequest): bool
    {
        if ($user->hasRole('REQUESTOR') && ! $user->hasRole('OE') && ! $user->hasRole('ACCT_STAFF')
            && ! $user->hasRole('ACCT_HEAD') && ! $user->hasRole('TREASURY')) {
            return (int) $fundRequest->requestor_id === (int) $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('REQUESTOR');
    }

    /**
     * Content (details, amount) is editable only by the Requestor, and only
     * while the record is Draft or Returned to Requestor — never once it has
     * left the Requestor's hands. amount_requested itself is never
     * client-editable at all; see FundRequestWorkflowService::recalculateAmount().
     */
    public function update(User $user, FundRequest $fundRequest): bool
    {
        return (int) $fundRequest->requestor_id === (int) $user->id
            && in_array($fundRequest->requestStatus->code, [
                RequestStatus::DRAFT,
                RequestStatus::RETURNED_TO_REQUESTOR,
            ], true);
    }

    public function validateAsOe(User $user, FundRequest $fundRequest): bool
    {
        return $user->hasRole('OE')
            && (int) $fundRequest->requestor_id !== (int) $user->id // segregation of duties, Phase 1 Problem P6
            && $this->oeIsInScope($user, $fundRequest)
            && in_array($fundRequest->requestStatus->code, [
                RequestStatus::FOR_OE_VALIDATION,
                RequestStatus::RETURNED_TO_OE,
            ], true);
    }

    public function validateAsStaff(User $user, FundRequest $fundRequest): bool
    {
        return $user->hasRole('ACCT_STAFF')
            && (int) $fundRequest->requestor_id !== (int) $user->id
            && in_array($fundRequest->requestStatus->code, [
                RequestStatus::FOR_ACCT_VALIDATION,
                RequestStatus::RETURNED_TO_STAFF,
            ], true);
    }

    public function approve(User $user, FundRequest $fundRequest): bool
    {
        return $user->hasRole('ACCT_HEAD')
            && (int) $fundRequest->requestor_id !== (int) $user->id
            && $fundRequest->requestStatus->code === RequestStatus::FOR_HEAD_APPROVAL;
    }

    public function createApVoucher(User $user, FundRequest $fundRequest): bool
    {
        return $user->hasRole('ACCT_STAFF')
            && $fundRequest->requestStatus->code === RequestStatus::FOR_AP_VOUCHER;
    }

    public function provideCvNumber(User $user, FundRequest $fundRequest): bool
    {
        return $user->hasRole('TREASURY')
            && $fundRequest->requestStatus->code === RequestStatus::FOR_CV_NUMBER;
    }

    public function disburse(User $user, FundRequest $fundRequest): bool
    {
        return $user->hasRole('TREASURY')
            && $fundRequest->requestStatus->code === RequestStatus::FOR_DISBURSEMENT;
    }

    public function cancel(User $user, FundRequest $fundRequest): bool
    {
        return (int) $fundRequest->requestor_id === (int) $user->id
            && in_array($fundRequest->requestStatus->code, [
                RequestStatus::DRAFT,
                RequestStatus::RETURNED_TO_REQUESTOR,
            ], true);
    }

    /**
     * Start the liquidation of a Cash Advance: only its Requestor, only once
     * the money has been disbursed (Completed), and only once per advance.
     */
    public function liquidate(User $user, FundRequest $fundRequest): bool
    {
        return (int) $fundRequest->requestor_id === (int) $user->id
            && $fundRequest->fundRequestType->code === \App\Models\FundRequestType::CASH_ADVANCE
            && $fundRequest->requestStatus->code === RequestStatus::COMPLETED
            && ! \App\Models\CashAdvanceLiquidation::where('fund_request_id', $fundRequest->id)->exists();
    }

    /**
     * Upload supporting documents: the Requestor while the request is editable,
     * and Treasury while it is For Disbursement (e.g. proof of payment).
     */
    public function attach(User $user, FundRequest $fundRequest): bool
    {
        return $this->update($user, $fundRequest) || $this->attachDisbursement($user, $fundRequest);
    }

    /** Treasury adds documents at the disbursement step. */
    public function attachDisbursement(User $user, FundRequest $fundRequest): bool
    {
        return $user->hasRole('TREASURY')
            && $fundRequest->requestStatus->code === RequestStatus::FOR_DISBURSEMENT;
    }

    /**
     * Remove a file: the Requestor in the editable window; Treasury only the
     * files they uploaded themselves, and only while it is For Disbursement.
     */
    public function removeAttachment(User $user, FundRequest $fundRequest, Attachment $attachment): bool
    {
        return $this->update($user, $fundRequest)
            || ($this->attachDisbursement($user, $fundRequest)
                && (int) $attachment->uploaded_by === (int) $user->id);
    }

    /**
     * Sea Base → vessel-scoped OE (user_vessels). Shore Base → department-
     * scoped OE (user_departments). Kept in sync with
     * FundRequestWorkflowService::assertOeScope() — same rule, enforced
     * twice: here to hide the button, there because a hidden button is
     * not a security control.
     */
    private function oeIsInScope(User $user, FundRequest $fundRequest): bool
    {
        return $fundRequest->vessel_id !== null
            ? $user->vessels()->where('vessels.id', $fundRequest->vessel_id)->exists()
            : $user->departments()->where('departments.id', $fundRequest->department_id)->exists();
    }
}
