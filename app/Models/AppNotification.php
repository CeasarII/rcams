<?php

namespace App\Models;

use App\Jobs\SendNotificationEmail;
use Illuminate\Database\Eloquent\Model;

/**
 * Named AppNotification (not Notification) to avoid clashing with
 * Illuminate\Notifications\Notification / the notifiable trait.
 *
 * The workflow services create these rows (reference_type is the record's
 * model class); the bell and the Notifications page read them.
 */
class AppNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id', 'title', 'message', 'reference_type', 'reference_id',
        'is_read', 'read_at',
    ];

    protected $casts = ['is_read' => 'boolean', 'read_at' => 'datetime'];

    /** Record type => [show route name, label shown to the user]. */
    private const TARGETS = [
        FundRequest::class => ['fund-requests.show', 'Cash advance'],
        CashAdvanceLiquidation::class => ['liquidations.show', 'Liquidation'],
        RevolvingFundExpense::class => ['revolving-fund-expenses.show', 'Voyage expense'],
        QuestionableExpense::class => ['questionable-expenses.show', 'Questionable expense'],
    ];

    /** Every new notification also goes out by email (see config/cams.php). */
    protected static function booted(): void
    {
        static::created(function (AppNotification $notification) {
            if (! config('cams.email_notifications')) {
                return;
            }

            config('cams.email_use_queue')
                ? SendNotificationEmail::dispatch($notification->id)
                : SendNotificationEmail::dispatchAfterResponse($notification->id);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function markAsRead(): void
    {
        if (! $this->is_read) {
            $this->forceFill(['is_read' => true, 'read_at' => now()])->save();
        }
    }

    /** What kind of record this is about, e.g. "Liquidation". */
    public function recordTypeLabel(): string
    {
        return self::TARGETS[$this->reference_type][1] ?? 'Record';
    }

    /** The record the notification is about, or null if it was removed or its type is unknown. */
    public function target(): ?Model
    {
        if (! isset(self::TARGETS[$this->reference_type])) {
            return null;
        }

        return $this->reference_type::find($this->reference_id);
    }

    /** The record's own page — only meaningful for a record returned by target(). */
    public function routeNameFor(): ?string
    {
        return self::TARGETS[$this->reference_type][0] ?? null;
    }

    /** Compact shape used by the bell dropdown. */
    public function toBellItem(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->recordTypeLabel(),
            'time' => $this->created_at->diffForHumans(['short' => true, 'parts' => 1]),
            'read' => $this->is_read,
            'url' => route('notifications.open', $this),
        ];
    }
}
