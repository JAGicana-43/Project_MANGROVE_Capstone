{{-- Shared by layouts/lobby.blade.php and layouts/workspace.blade.php.
     $unreadNotifications / $unreadNotificationCount come from the view
     composer in AppServiceProvider::boot() -- always defined on both
     layouts, so no @isset guard needed here. --}}
<div class="notif-cluster">
    <button class="icon-btn" id="notifBellToggle" type="button" title="Notifications" aria-haspopup="true" aria-expanded="false">
        <i class="fa-solid fa-bell"></i>
        @if($unreadNotificationCount > 0)
            <em class="notif-badge" id="notifBadge">{{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}</em>
        @endif
    </button>

    <div class="notif-dropdown" id="notifDropdown" hidden>
        <div class="notif-dropdown-header">
            <span>Notifications</span>
        </div>
        <div class="notif-list" id="notifList">
            @forelse($unreadNotifications as $notification)
                <a class="notif-item" href="{{ $notification->data['url'] ?? '#' }}">
                    <i class="fa-solid fa-flask"></i>
                    <span>
                        <b>Point #{{ $notification->data['point_rank'] ?? '?' }} collected</b>
                        <em>{{ $notification->data['aoi_name'] ?? 'Unknown area' }} &middot; {{ $notification->data['project_name'] ?? 'Unknown project' }}</em>
                        <small>{{ $notification->created_at->diffForHumans() }}</small>
                    </span>
                </a>
            @empty
                <p class="notif-empty">No new notifications.</p>
            @endforelse
        </div>
    </div>
</div>

<style>
    .notif-cluster {
        position: relative;
    }

    .icon-btn {
        position: relative;
    }

    .notif-badge {
        position: absolute;
        top: -3px;
        right: -3px;
        min-width: 16px;
        padding: 0 4px;
        border-radius: 999px;
        background: #B42318;
        border: 2px solid var(--surface);
        font-size: 10px;
        font-style: normal;
        font-weight: 700;
        line-height: 14px;
        text-align: center;
        color: #fff;
    }

    .notif-dropdown {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        z-index: 1300;
        width: min(340px, calc(100vw - 32px));
        max-height: 420px;
        overflow-y: auto;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        border-radius: 12px;
        box-shadow: 0 20px 44px hsl(var(--shadow-color) / 0.22);
        transform-origin: top right;
        animation: motionMenuIn 0.18s cubic-bezier(0.22, 1, 0.36, 1);
    }

    .notif-dropdown-header {
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-soft);
        font-family: 'Poppins', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .notif-list {
        display: flex;
        flex-direction: column;
        padding: 6px;
    }

    .notif-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px;
        border-radius: 9px;
        text-decoration: none;
        color: inherit;
    }

    .notif-item:hover {
        background: var(--off-white);
    }

    .notif-item i {
        flex: none;
        margin-top: 2px;
        width: 26px;
        height: 26px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: rgba(64, 145, 108, .12);
        color: var(--teal);
        font-size: 11px;
    }

    .notif-item span {
        display: flex;
        flex-direction: column;
        gap: 1px;
        min-width: 0;
    }

    .notif-item b {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .notif-item em {
        font-size: 11.5px;
        font-style: normal;
        color: var(--text-muted);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .notif-item small {
        margin-top: 2px;
        font-size: 10.5px;
        color: var(--text-muted);
    }

    .notif-empty {
        padding: 20px 14px;
        text-align: center;
        font-size: 12.5px;
        color: var(--text-muted);
    }
</style>

<script>
    (function () {
        var toggle = document.getElementById('notifBellToggle');
        var dropdown = document.getElementById('notifDropdown');
        var badge = document.getElementById('notifBadge');
        if (!toggle || !dropdown) return;

        var markedRead = false;

        function closeDropdown() {
            dropdown.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        }

        function openDropdown() {
            dropdown.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');

            // Fire-and-forget: clears the badge without waiting on the
            // request, and only once per page load (reopening the same
            // dropdown shouldn't re-POST).
            if (!markedRead) {
                markedRead = true;
                if (badge) badge.remove();
                fetch('{{ route('notifications.read-all') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                }).catch(function () {});
            }
        }

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            if (dropdown.hidden) openDropdown(); else closeDropdown();
        });

        document.addEventListener('click', function (event) {
            if (!dropdown.hidden && !dropdown.contains(event.target) && event.target !== toggle) {
                closeDropdown();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeDropdown();
        });
    })();
</script>
