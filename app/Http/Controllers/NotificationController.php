<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Everyone sees only their own notifications. The workflow services create
 * them; this controller lists them, feeds the bell, and sends the user to
 * the record a notification is about.
 */
class NotificationController extends Controller
{
    /** The full "Notifications" page. */
    public function index(Request $request)
    {
        $userId = Auth::id();
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        $query = AppNotification::forUser($userId)->latest('id');
        if ($filter === 'unread') {
            $query->unread();
        }

        return view('notifications.index', [
            'notifications' => $query->paginate(20)->withQueryString(),
            'filter' => $filter,
            'unreadCount' => AppNotification::forUser($userId)->unread()->count(),
            'totalCount' => AppNotification::forUser($userId)->count(),
        ]);
    }

    /** JSON for the bell: unread count plus the latest few. Polled by the top bar. */
    public function feed()
    {
        $userId = Auth::id();

        return response()->json([
            'unread' => AppNotification::forUser($userId)->unread()->count(),
            'items' => AppNotification::forUser($userId)->latest('id')->limit(8)->get()
                ->map(fn (AppNotification $n) => $n->toBellItem())->all(),
        ]);
    }

    /** Mark as read and go to the record the notification is about. */
    public function open(AppNotification $notification)
    {
        abort_unless((int) $notification->user_id === (int) Auth::id(), 404);

        $notification->markAsRead();

        $record = $notification->target();
        if ($record && Gate::allows('view', $record)) {
            return redirect()->route($notification->routeNameFor(), $record);
        }

        return redirect()->route('notifications.index')
            ->withErrors(['access' => 'That record is no longer available to you.']);
    }

    public function readAll()
    {
        AppNotification::forUser(Auth::id())->unread()->update(['is_read' => true, 'read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}
