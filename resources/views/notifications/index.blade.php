@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title mb-0">Notifications</h1>
            <div class="text-muted small">{{ $unreadCount }} unread · {{ $totalCount }} total</div>
        </div>
        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}" class="mb-0">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">Mark all as read</button>
            </form>
        @endif
    </div>

    <ul class="nav nav-pills mb-3 gap-1">
        <li class="nav-item">
            <a class="nav-link py-1 {{ $filter === 'all' ? 'active' : '' }}" href="{{ route('notifications.index') }}">All</a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 {{ $filter === 'unread' ? 'active' : '' }}" href="{{ route('notifications.index', ['filter' => 'unread']) }}">
                Unread @if ($unreadCount > 0)<span class="badge text-bg-light border ms-1">{{ $unreadCount }}</span>@endif
            </a>
        </li>
    </ul>

    <div class="card">
        @forelse ($notifications as $n)
            <a href="{{ route('notifications.open', $n) }}" class="notif-row {{ $n->is_read ? '' : 'unread' }}">
                <span class="notif-dot"></span>
                <div class="notif-row-body">
                    <div class="d-flex flex-wrap justify-content-between gap-1">
                        <span class="notif-title">{{ $n->title }}</span>
                        <span class="notif-time" title="{{ $n->created_at->timezone(config('app.display_timezone', 'Asia/Manila'))->format('M d, Y g:i A') }}">
                            {{ $n->created_at->timezone(config('app.display_timezone', 'Asia/Manila'))->format('M d, Y g:i A') }}
                        </span>
                    </div>
                    <div class="notif-msg-full">{{ $n->message }}</div>
                    <span class="badge text-bg-light border fw-normal mt-1">{{ $n->recordTypeLabel() }}</span>
                </div>
            </a>
        @empty
            <div class="card-body text-center text-muted py-5">
                {{ $filter === 'unread' ? 'No unread notifications.' : 'No notifications yet. You will be notified here when a request needs your action.' }}
            </div>
        @endforelse
    </div>

    <div class="mt-3">{{ $notifications->links() }}</div>
@endsection
