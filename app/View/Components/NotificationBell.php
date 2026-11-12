<?php

namespace App\View\Components;

use App\Models\AppNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

/** The bell in the top bar. Starts with the current state; the page then keeps it fresh by polling. */
class NotificationBell extends Component
{
    public array $initial;

    public function __construct()
    {
        $userId = Auth::id();

        $this->initial = [
            'unread' => AppNotification::forUser($userId)->unread()->count(),
            'items' => AppNotification::forUser($userId)->latest('id')->limit(8)->get()
                ->map(fn (AppNotification $n) => $n->toBellItem())->all(),
        ];
    }

    public function render(): View
    {
        return view('components.notification-bell');
    }
}
