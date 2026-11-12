<?php

namespace App\Jobs;

use App\Mail\WorkflowNotificationMail;
use App\Models\AppNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails one notification. A mail problem (server down, bad address) must never
 * break the workflow action that caused it, so when this runs inline it only
 * logs the failure; on a real queue it is rethrown so the job is retried.
 */
class SendNotificationEmail implements ShouldQueue
{
    use Queueable;

    /** Queued mode: wait until the surrounding database transaction has committed. */
    public bool $afterCommit = true;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $notificationId)
    {
    }

    public function handle(): void
    {
        $notification = AppNotification::with('user')->find($this->notificationId);

        // Gone (the action was rolled back), or the person already saw it in the app.
        if (! $notification || $notification->is_read) {
            return;
        }

        $user = $notification->user;
        if (! $user || ! $user->is_active || blank($user->email)) {
            return;
        }

        try {
            Mail::to($user->email)->send(new WorkflowNotificationMail($notification));
        } catch (Throwable $e) {
            if ($this->job === null) { // running inline, not from a queue worker
                report($e);

                return;
            }

            throw $e;
        }
    }
}
