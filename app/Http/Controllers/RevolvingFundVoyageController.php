<?php

namespace App\Http\Controllers;

use App\Models\RevolvingFund;
use App\Models\RevolvingFundExpenseStatus;
use App\Models\RevolvingFundVoyage;
use App\Models\RevolvingFundVoyageStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Voyage tracking: the custodian opens a voyage (beginning balance) and closes it (ending balance). */
class RevolvingFundVoyageController extends Controller
{
    public function store(Request $request, RevolvingFund $revolvingFund)
    {
        $this->authorize('manageVoyage', $revolvingFund);

        $data = $request->validate([
            'voyage_no' => ['required', 'string', 'max:30',
                Rule::unique('revolving_fund_voyages', 'voyage_no')->where('revolving_fund_id', $revolvingFund->id)],
            'start_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        if ($revolvingFund->openVoyage()) {
            throw ValidationException::withMessages(['voyage_no' => 'Close the current voyage before starting a new one.']);
        }

        $voyage = RevolvingFundVoyage::create([
            'revolving_fund_id' => $revolvingFund->id,
            'voyage_no' => $data['voyage_no'],
            'start_date' => $data['start_date'],
            'beginning_balance' => $revolvingFund->current_balance,
            'status_id' => RevolvingFundVoyageStatus::where('code', RevolvingFundVoyageStatus::OPEN)->firstOrFail()->id,
        ]);

        return back()->with('status', "Voyage {$voyage->voyage_no} started.");
    }

    public function end(Request $request, RevolvingFund $revolvingFund, RevolvingFundVoyage $voyage)
    {
        $this->authorize('manageVoyage', $revolvingFund);
        abort_unless((int) $voyage->revolving_fund_id === (int) $revolvingFund->id, 404);

        if (! $voyage->isOpen()) {
            throw ValidationException::withMessages(['end_date' => 'This voyage is already closed.']);
        }

        $data = $request->validate([
            'end_date' => ['required', 'date', 'after_or_equal:'.$voyage->start_date->toDateString(), 'before_or_equal:today'],
        ]);

        // The ending balance is a snapshot of the fund, and an expense only moves the fund when the
        // Head approves it (and again at disbursement). So every voyage expense must be Completed
        // first, otherwise it would post to the fund after the ending balance was already frozen.
        $unfinished = $voyage->expenses()
            ->whereHas('status', fn ($q) => $q->where('code', '!=', RevolvingFundExpenseStatus::COMPLETED))
            ->with('status')
            ->get();
        if ($unfinished->isNotEmpty()) {
            $list = $unfinished->map(fn ($e) => "{$e->control_no} ({$e->status->label})")->implode(', ');

            throw ValidationException::withMessages([
                'end_date' => "Complete all voyage expenses before closing the voyage. Still in progress: {$list}.",
            ]);
        }

        $voyage->update([
            'end_date' => $data['end_date'],
            'ending_balance' => $revolvingFund->fresh()->current_balance,
            'status_id' => RevolvingFundVoyageStatus::where('code', RevolvingFundVoyageStatus::CLOSED)->firstOrFail()->id,
        ]);

        return back()->with('status', "Voyage {$voyage->voyage_no} closed.");
    }
}
