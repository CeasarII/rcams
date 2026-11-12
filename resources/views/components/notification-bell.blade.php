<div class="dropdown notif" id="notif-bell" data-feed="{{ route('notifications.feed') }}">
    <button type="button" class="notif-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
        <span class="notif-badge" data-badge hidden></span>
    </button>

    <div class="dropdown-menu dropdown-menu-end notif-panel">
        <div class="notif-panel-head">
            <span class="fw-semibold">Notifications</span>
            <form method="POST" action="{{ route('notifications.read-all') }}" class="mb-0">
                @csrf
                <button type="submit" class="notif-link-btn" data-readall hidden>Mark all as read</button>
            </form>
        </div>

        <div class="notif-list" data-list></div>

        <a href="{{ route('notifications.index') }}" class="notif-panel-foot">View all notifications</a>
    </div>

    <script type="application/json" data-initial>@json($initial)</script>
    <script>
    (function () {
        const root = document.getElementById('notif-bell');
        if (!root) return;

        const badge = root.querySelector('[data-badge]');
        const list = root.querySelector('[data-list]');
        const readAll = root.querySelector('[data-readall]');

        function render(data) {
            const unread = data.unread || 0;
            badge.textContent = unread > 99 ? '99+' : String(unread);
            badge.hidden = unread === 0;
            readAll.hidden = unread === 0;

            const rows = (data.items || []).map(function (n) {
                const a = document.createElement('a');
                a.href = n.url;
                a.className = 'notif-item' + (n.read ? '' : ' unread');

                const dot = document.createElement('span');
                dot.className = 'notif-dot';

                const body = document.createElement('div');
                body.className = 'notif-body';

                const top = document.createElement('div');
                top.className = 'notif-top';
                const title = document.createElement('span');
                title.className = 'notif-title';
                title.textContent = n.title;
                const time = document.createElement('span');
                time.className = 'notif-time';
                time.textContent = n.time;
                top.append(title, time);

                const msg = document.createElement('div');
                msg.className = 'notif-msg';
                msg.textContent = n.message;

                body.append(top, msg);
                a.append(dot, body);

                return a;
            });

            if (rows.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'notif-empty';
                empty.textContent = "You're all caught up.";
                list.replaceChildren(empty);
            } else {
                list.replaceChildren(...rows);
            }
        }

        async function refresh() {
            try {
                const res = await fetch(root.dataset.feed, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (res.ok) render(await res.json());
            } catch (e) { /* offline or session ended: keep what is on screen */ }
        }

        render(JSON.parse(root.querySelector('[data-initial]').textContent));

        setInterval(function () { if (!document.hidden) refresh(); }, 60000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
        root.addEventListener('show.bs.dropdown', refresh);
    })();
    </script>
</div>
