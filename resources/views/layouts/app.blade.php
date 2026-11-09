<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Cash Advance Monitoring System')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
<div class="app-shell" id="app-shell">

    <aside class="sidebar" id="app-sidebar">
        <div class="sidebar-brand">
            <span class="mark">CA</span>
            <span class="label">CAMS</span>
            <button type="button" class="sidebar-toggle" id="sidebar-collapse-btn" aria-label="Collapse sidebar">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/></svg>
            </button>
        </div>

        <button type="button" class="sidebar-expand-toggle" id="sidebar-expand-btn" aria-label="Expand sidebar">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/></svg>
        </button>

        @auth
            <x-sidebar-nav />
        @endauth

        @auth
            <div class="sidebar-footer">
                <form method="POST" action="{{ route('logout') }}" class="mb-0">
                    @csrf
                    <button type="submit" class="sidebar-link w-100 border-0 bg-transparent text-start">
                        <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                        </span>
                        <span class="label">Log Out</span>
                    </button>
                </form>
            </div>
        @endauth
    </aside>

    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

    <div class="app-main">
        <header class="topbar">
            <button type="button" class="topbar-menu-btn" id="sidebar-mobile-btn" aria-label="Open menu" aria-controls="app-sidebar" aria-expanded="false">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <span class="topbar-title">@yield('title', 'Cash Advance Monitoring System')</span>
            @auth
                <span class="topbar-user">{{ auth()->user()->full_name }}</span>
            @endauth
        </header>

        <main class="content-area">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<x-attachment-viewer />

<script>
(function () {
    const shell = document.getElementById('app-shell');
    const collapseBtn = document.getElementById('sidebar-collapse-btn');
    const expandBtn = document.getElementById('sidebar-expand-btn');
    const mobileBtn = document.getElementById('sidebar-mobile-btn');
    const backdrop = document.getElementById('sidebar-backdrop');
    const sidebar = document.getElementById('app-sidebar');
    const STORAGE_KEY = 'cams-sidebar-collapsed';
    const mobileQuery = window.matchMedia('(max-width: 860px)');

    function setMenu(open) {
        shell.classList.toggle('sidebar-open', open);
        document.body.classList.toggle('menu-open', open);
        mobileBtn?.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    try {
        if (localStorage.getItem(STORAGE_KEY) === '1') {
            shell.classList.add('sidebar-collapsed');
        }
    } catch (e) { /* storage blocked: just start expanded */ }

    function remember(value) {
        try { localStorage.setItem(STORAGE_KEY, value); } catch (e) {}
    }

    // On desktop this button collapses the sidebar to icons; on a phone the
    // sidebar is an overlay, so the same button simply closes it.
    collapseBtn?.addEventListener('click', function () {
        if (mobileQuery.matches) {
            setMenu(false);
            return;
        }
        shell.classList.add('sidebar-collapsed');
        remember('1');
    });

    expandBtn?.addEventListener('click', function () {
        shell.classList.remove('sidebar-collapsed');
        remember('0');
    });

    mobileBtn?.addEventListener('click', function () {
        setMenu(! shell.classList.contains('sidebar-open'));
    });

    backdrop?.addEventListener('click', function () { setMenu(false); });

    // Tapping a menu link, or pressing Escape, closes the overlay.
    sidebar?.addEventListener('click', function (e) {
        if (mobileQuery.matches && e.target.closest('a')) setMenu(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && shell.classList.contains('sidebar-open')) setMenu(false);
    });

    // Growing the window past the breakpoint (e.g. rotating a tablet) must not
    // leave the page scroll-locked.
    mobileQuery.addEventListener('change', function () { setMenu(false); });
})();
</script>
</body>
</html>
