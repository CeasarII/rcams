<?php

namespace App\Mail;

use App\Models\AppNotification;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Email copy of one in-app notification. The button signs the person in if needed, then opens the record. */
class WorkflowNotificationMail extends Mailable
{
    public function __construct(public AppNotification $notification)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.config('app.name').'] '.$this->notification->title);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.workflow-notification',
            with: [
                'title' => $this->notification->title,
                'body' => $this->notification->message,
                'type' => $this->notification->recordTypeLabel(),
                'url' => route('notifications.open', $this->notification),
            ],
        );
    }
}
