<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Console') | Project M.A.N.G.R.O.V.E</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/favicon-badge.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo/favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo/apple-touch-icon.png') }}">

    <script>
        (function () {
            var savedTheme = localStorage.getItem('workspaceTheme');
            if (savedTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        :root {
            --forest-green: #2D6A4F;
            --forest-green-dark: #1B4332;
            --sage-green: #74C69D;
            --teal: #40916C;
            --amber: #F0A500;
            --danger: #B42318;
            --danger-soft: #FFF1EF;
            --navbar-height: 72px;
            --sidebar-width: 264px;
            --sidebar-collapsed: 74px;
            --ink-rgb: 33,37,41;
            --surface-rgb: 255,255,255;
            --paper-rgb: 248,249,250;
            --off-white: rgb(var(--paper-rgb));
            --dark-gray: rgb(var(--ink-rgb));
            --surface: rgb(var(--surface-rgb));
            --glass: rgba(var(--surface-rgb), 0.85);
            --panel-shadow: 0 14px 36px rgba(var(--ink-rgb), 0.14);
            --border-soft: rgba(var(--ink-rgb), 0.10);
            --accent-green: var(--forest-green);
        }

        [data-theme="dark"] {
            --ink-rgb: 222,230,226;
            --surface-rgb: 28,38,34;
            --paper-rgb: 15,22,19;
            --panel-shadow: 0 14px 36px rgba(0,0,0,0.5);
            --accent-green: #6FCF97;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { min-height: 100%; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--off-white);
            color: var(--dark-gray);
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            opacity: 0.05;
            background-image:
                repeating-radial-gradient(circle at 10% 8%, transparent 0, transparent 26px, var(--forest-green) 27px, transparent 28px),
                repeating-radial-gradient(circle at 92% 90%, transparent 0, transparent 34px, var(--teal) 35px, transparent 36px);
            background-size: 480px 480px, 620px 620px;
        }

        h1, h2, h3, h4, h5, h6 { font-family: 'Poppins', sans-serif; }
        a { color: inherit; text-decoration: none; }
        button { font: inherit; }

        .glass-panel {
            background: var(--glass);
            border: 1px solid rgba(var(--surface-rgb),0.72);
            border-radius: 16px;
            box-shadow: var(--panel-shadow);
            backdrop-filter: blur(16px);
        }

        /* ============ NAVBAR ============ */
        .gis-navbar {
            position: fixed;
            inset: 0 0 auto 0;
            height: var(--navbar-height);
            z-index: 1200;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 0 18px;
            background: rgba(var(--paper-rgb),0.92);
            border-bottom: 1px solid var(--border-soft);
            box-shadow: 0 8px 22px rgba(var(--ink-rgb),0.08);
            backdrop-filter: blur(16px);
        }

        .toolbar-side, .brand-cluster, .user-cluster { display: flex; align-items: center; }
        .toolbar-side { gap: 12px; min-width: 0; }
        .brand-cluster { gap: 10px; min-width: 0; }
        .user-cluster { gap: 12px; }

        .brand-mark {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            box-shadow: 0 10px 20px rgba(45,106,79,0.24);
            padding: 5px;
            box-sizing: border-box;
            flex-shrink: 0;
        }
        .brand-mark img { width: 100%; height: 100%; object-fit: contain; display: block; }

        .brand-copy { display: flex; align-items: baseline; gap: 8px; min-width: 0; white-space: nowrap; }
        .app-name { color: var(--accent-green); font-family: 'Poppins', sans-serif; font-size: 16px; font-weight: 700; }
        .toolbar-divider { color: rgba(var(--ink-rgb),0.32); }
        .project-name { color: var(--dark-gray); font-size: 14px; font-weight: 600; }

        .icon-btn {
            width: 38px;
            height: 38px;
            border: 1px solid rgba(45,106,79,0.18);
            border-radius: 12px;
            background: var(--glass);
            color: var(--accent-green);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(var(--ink-rgb),0.08);
            transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease;
        }

        .icon-btn:hover { background: var(--forest-green); color: #fff; transform: translateY(-1px); }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--sage-green);
            color: var(--forest-green-dark);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            flex: 0 0 auto;
        }

        .user-meta { display: flex; flex-direction: column; line-height: 1.2; }
        .user-name { font-size: 13px; font-weight: 700; }
        .role-badge {
            width: fit-content;
            padding: 2px 9px;
            margin-top: 2px;
            border-radius: 999px;
            background: rgba(116,198,157,0.24);
            color: var(--accent-green);
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .logout-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            background: var(--glass);
            color: var(--dark-gray);
            padding: 9px 12px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.15s ease;
        }

        .logout-btn:hover { color: var(--danger); background: var(--surface); border-color: #F3C9C9; }

        /* ============ SHELL / SIDEBAR ============ */
        .admin-shell {
            position: relative;
            min-height: 100vh;
            padding-top: var(--navbar-height);
        }

        .sidebar {
            position: fixed;
            top: calc(var(--navbar-height) + 18px);
            left: 18px;
            bottom: 18px;
            z-index: 900;
            width: var(--sidebar-width);
            overflow: hidden;
            transition: width 0.22s ease;
        }

        .sidebar.collapsed { width: var(--sidebar-collapsed); }

        .sidebar-inner { height: 100%; display: flex; flex-direction: column; padding: 14px; }

        .sidebar-title {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 42px;
            padding: 3px 4px 12px;
            border-bottom: 1px solid var(--border-soft);
        }

        .sidebar-title-icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: rgba(45,106,79,0.12);
            color: var(--accent-green);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }

        .sidebar-title-text {
            min-width: 0;
            font-size: 13px;
            font-weight: 800;
            color: var(--accent-green);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-nav { list-style: none; display: flex; flex-direction: column; gap: 6px; padding: 14px 0 0; overflow-y: auto; }

        .nav-link {
            position: relative;
            min-height: 44px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 12px;
            border-radius: 13px;
            color: rgba(var(--ink-rgb),0.82);
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
            transition: background 0.18s ease, color 0.18s ease;
        }

        .nav-link i { width: 22px; text-align: center; color: var(--accent-green); flex: 0 0 auto; }
        .nav-link:hover { background: rgba(45,106,79,0.10); color: var(--accent-green); }
        .nav-link.active { background: var(--forest-green); color: #fff; box-shadow: 0 10px 18px rgba(45,106,79,0.22); }
        .nav-link.active i { color: #fff; }

        .nav-badge {
            margin-left: auto;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: var(--amber);
            color: #fff;
            font-size: 11px;
            font-weight: 800;
        }

        .nav-link.active .nav-badge { background: rgba(255,255,255,0.28); }

        .sidebar.collapsed .sidebar-title-text,
        .sidebar.collapsed .nav-label,
        .sidebar.collapsed .nav-badge { display: none; }
        .sidebar.collapsed .sidebar-inner { padding: 14px 10px; }
        .sidebar.collapsed .sidebar-title { justify-content: center; padding-left: 0; padding-right: 0; }
        .sidebar.collapsed .nav-link { justify-content: center; padding: 0; }

        .admin-main {
            margin-left: calc(18px + var(--sidebar-width) + 18px);
            padding: 24px 24px 56px;
            min-height: calc(100vh - var(--navbar-height));
            transition: margin-left 0.22s ease;
        }

        .sidebar.collapsed ~ .admin-main {
            margin-left: calc(18px + var(--sidebar-collapsed) + 18px);
        }

        .admin-main-inner { max-width: 1180px; margin: 0 auto; }

        @media (max-width: 900px) {
            .sidebar {
                top: calc(var(--navbar-height) + 12px);
                left: 12px;
                bottom: auto;
                height: auto;
                width: var(--sidebar-collapsed);
                max-height: calc(100vh - var(--navbar-height) - 24px);
            }
            .sidebar .sidebar-title-text, .sidebar .nav-label, .sidebar .nav-badge { display: none; }
            .sidebar .sidebar-inner { padding: 10px 8px; }
            .sidebar .sidebar-title { justify-content: center; padding: 0 0 10px; }
            .sidebar .nav-link { justify-content: center; padding: 0; }
            .admin-main, .sidebar.collapsed ~ .admin-main { margin-left: 86px; padding: 18px 16px 48px; }
            .project-name, .toolbar-divider, .user-meta { display: none; }
        }

        /* ============ SHARED UI: stat tiles, cards, buttons, toast ============ */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .stat-tile {
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .stat-tile-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex: 0 0 auto;
            color: var(--accent-green);
            background: rgba(45,106,79,0.12);
        }

        .stat-tile.warn .stat-tile-icon { color: var(--amber); background: rgba(240,165,0,0.14); }
        .stat-tile.danger .stat-tile-icon { color: var(--danger); background: rgba(180,35,24,0.10); }

        .stat-tile-value { font-family: 'Poppins', sans-serif; font-size: 24px; font-weight: 800; line-height: 1.1; }
        .stat-tile-label { color: rgba(var(--ink-rgb),0.6); font-size: 12.5px; font-weight: 700; margin-top: 2px; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 11px;
            border: 1px solid var(--border-soft);
            background: var(--surface);
            color: var(--dark-gray);
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-primary { background: var(--forest-green); border-color: var(--forest-green); color: #fff; box-shadow: 0 8px 16px rgba(45,106,79,0.2); }
        .btn-primary:hover { background: var(--forest-green-dark); }
        .btn-danger { background: var(--danger-soft); border-color: #FFD2CC; color: var(--danger); }
        .btn-danger:hover { background: #ffe4e0; }
        .btn-ghost { background: rgba(var(--ink-rgb),0.05); border-color: transparent; }
        .btn:disabled { opacity: 0.55; cursor: wait; transform: none; }
        .btn-sm { padding: 7px 11px; font-size: 12.5px; border-radius: 9px; }

        .page-header { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
        .page-header h1 { font-size: 22px; margin-bottom: 4px; }
        .page-header p { color: rgba(var(--ink-rgb),0.62); font-size: 13.5px; }

        .toast-stack {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 3000;
            display: flex;
            flex-direction: column-reverse;
            align-items: center;
            gap: 10px;
            pointer-events: none;
        }

        .toast {
            min-width: 260px;
            max-width: 340px;
            padding: 12px 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            font-weight: 700;
            color: var(--dark-gray);
            animation: toastIn 0.22s ease both;
        }

        .toast i { margin-top: 1px; }
        .toast.success i { color: var(--accent-green); }
        .toast.error i { color: var(--danger); }

        @keyframes toastIn {
            from { opacity: 0; transform: translateY(8px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        [x-cloak] { display: none !important; }

        @media (prefers-reduced-motion: reduce) {
            .toast { animation: none; }
        }
    </style>
    @include('partials.page-transition-styles')
    @include('partials.motion-styles')
</head>
<body>
    @include('partials.page-transition')
    @include('partials.logout-modal')

    @php
        $adminUser = $currentUser ?? auth()->user();
        $adminInitial = strtoupper(substr($adminUser->name ?? 'A', 0, 1));
        $adminNavRoute = $adminNavRoute ?? 'dashboard';
        $adminPendingCount = $adminPendingCount ?? 0;

        $adminNavItems = [
            ['label' => 'Dashboard', 'icon' => 'fa-gauge-high', 'route' => 'dashboard', 'url' => route('admin.dashboard')],
            ['label' => 'User Management', 'icon' => 'fa-users-gear', 'route' => 'users', 'url' => route('admin.users.index'), 'badge' => $adminPendingCount],
            ['label' => 'Audit Logs', 'icon' => 'fa-clipboard-list', 'route' => 'audit-logs', 'url' => route('admin.audit-logs.index')],
            ['label' => 'Settings', 'icon' => 'fa-sliders', 'route' => 'settings', 'url' => route('admin.settings.index')],
        ];
    @endphp

    <nav class="gis-navbar">
        <div class="toolbar-side">
            <button class="icon-btn" id="adminSidebarToggle" type="button" title="Toggle sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="brand-cluster">
                <span class="brand-mark"><img src="{{ asset('images/logo/ProjMangrove_Logo.svg') }}" alt="Project M.A.N.G.R.O.V.E logo"></span>
                <div class="brand-copy">
                    <span class="app-name">Project M.A.N.G.R.O.V.E</span>
                    <span class="toolbar-divider">/</span>
                    <span class="project-name">Admin Console</span>
                </div>
            </div>
        </div>

        <div class="user-cluster">
            <button class="icon-btn" id="themeToggle" type="button" title="Toggle dark mode">
                <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
            </button>
            <button class="icon-btn" id="soundToggle" type="button" title="Mute interface sounds">
                <i class="fa-solid fa-volume-high" id="soundToggleIcon"></i>
            </button>
            <a href="{{ route('settings.account') }}" class="icon-btn" title="Account Settings">
                <i class="fa-solid fa-gear"></i>
            </a>
            <div class="user-avatar">{{ $adminInitial }}</div>
            <div class="user-meta">
                <span class="user-name">{{ $adminUser->name ?? 'Administrator' }}</span>
                <span class="role-badge">Administrator</span>
            </div>
            <form method="POST" action="{{ url('/logout') }}" style="margin:0;" data-loading-label="Logging out...">
                @csrf
                <button type="submit" class="logout-btn" onclick="return openLogoutModal(event)">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Log out</span>
                </button>
            </form>
        </div>
    </nav>

    @include('partials.password-warning-banner')

    <div class="admin-shell">
        <aside class="sidebar glass-panel" id="adminSidebar">
            <div class="sidebar-inner">
                <div class="sidebar-title">
                    <span class="sidebar-title-icon"><i class="fa-solid fa-shield-halved"></i></span>
                    <span class="sidebar-title-text">Admin Console</span>
                </div>
                <ul class="sidebar-nav">
                    @foreach ($adminNavItems as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="nav-link {{ $adminNavRoute === $item['route'] ? 'active' : '' }}">
                                <i class="fa-solid {{ $item['icon'] }}"></i>
                                <span class="nav-label">{{ $item['label'] }}</span>
                                @if (!empty($item['badge']))
                                    <span class="nav-badge">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>

        <main class="admin-main">
            <div class="admin-main-inner">
                @yield('content')
            </div>
        </main>
    </div>

    <div class="toast-stack" id="toastStack" aria-live="polite"></div>

    <script>
        // Short synthesized feedback sounds for confirmations/state changes
        // (no audio assets needed). Muted state persists via localStorage.
        (function () {
            var STORAGE_KEY = 'uiSoundMuted';
            var ctx = null;

            function isMuted() {
                return localStorage.getItem(STORAGE_KEY) === '1';
            }

            function getContext() {
                var AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return null;
                if (!ctx) ctx = new AudioCtx();
                if (ctx.state === 'suspended') ctx.resume();
                return ctx;
            }

            function tone(audioCtx, freq, start, duration, peakGain, waveform) {
                var osc = audioCtx.createOscillator();
                var gain = audioCtx.createGain();
                osc.type = waveform || 'sine';
                osc.frequency.value = freq;
                var t0 = audioCtx.currentTime + start;
                gain.gain.setValueAtTime(0, t0);
                gain.gain.linearRampToValueAtTime(peakGain, t0 + 0.015);
                gain.gain.exponentialRampToValueAtTime(0.0001, t0 + duration);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start(t0);
                osc.stop(t0 + duration + 0.02);
            }

            var PRESETS = {
                success: function (audioCtx) {
                    tone(audioCtx, 880, 0, 0.12, 0.12);
                    tone(audioCtx, 1318.5, 0.09, 0.16, 0.1);
                },
                error: function (audioCtx) {
                    tone(audioCtx, 320, 0, 0.16, 0.12, 'triangle');
                    tone(audioCtx, 220, 0.1, 0.2, 0.1, 'triangle');
                },
                notify: function (audioCtx) {
                    tone(audioCtx, 660, 0, 0.14, 0.1);
                }
            };

            window.uiSound = {
                play: function (name) {
                    if (isMuted()) return;
                    var audioCtx = getContext();
                    if (!audioCtx) return;
                    var preset = PRESETS[name] || PRESETS.notify;
                    try { preset(audioCtx); } catch (e) {}
                },
                isMuted: isMuted,
                setMuted: function (muted) {
                    localStorage.setItem(STORAGE_KEY, muted ? '1' : '0');
                },
                toggleMute: function () {
                    var next = !isMuted();
                    localStorage.setItem(STORAGE_KEY, next ? '1' : '0');
                    return next;
                }
            };

            document.addEventListener('DOMContentLoaded', function () {
                var toggleBtn = document.getElementById('soundToggle');
                var toggleIcon = document.getElementById('soundToggleIcon');
                if (!toggleBtn || !toggleIcon) return;

                function applyIcon(muted) {
                    toggleIcon.className = muted ? 'fa-solid fa-volume-xmark' : 'fa-solid fa-volume-high';
                    toggleBtn.title = muted ? 'Unmute interface sounds' : 'Mute interface sounds';
                }

                applyIcon(isMuted());

                toggleBtn.addEventListener('click', function () {
                    var nowMuted = window.uiSound.toggleMute();
                    applyIcon(nowMuted);
                    if (!nowMuted) window.uiSound.play('notify');
                });
            });
        })();

        window.adminToast = function (message, type) {
            var stack = document.getElementById('toastStack');
            if (!stack) return;
            var toast = document.createElement('div');
            toast.className = 'glass-panel toast ' + (type || 'success');
            toast.innerHTML = '<i class="fa-solid ' + (type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check') + '"></i><span>' + message + '</span>';
            stack.appendChild(toast);
            window.uiSound.play(type === 'error' ? 'error' : 'success');
            window.setTimeout(function () {
                toast.style.transition = 'opacity 0.2s ease';
                toast.style.opacity = '0';
                window.setTimeout(function () { toast.remove(); }, 200);
            }, 3400);
        };

        window.adminCsrfToken = @json(csrf_token());

        (function () {
            var toggleBtn = document.getElementById('themeToggle');
            var toggleIcon = document.getElementById('themeToggleIcon');

            function applyIcon(theme) {
                if (toggleIcon) toggleIcon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
                // Icon-only button here (no visible label), but the title
                // tooltip gets the same "switches TO" phrasing the lobby/
                // workspace menu item's text uses.
                if (toggleBtn) toggleBtn.title = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
            }

            applyIcon(document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function () {
                    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                    var nextTheme = isDark ? 'light' : 'dark';

                    if (nextTheme === 'dark') {
                        document.documentElement.setAttribute('data-theme', 'dark');
                    } else {
                        document.documentElement.removeAttribute('data-theme');
                    }

                    localStorage.setItem('workspaceTheme', nextTheme);
                    applyIcon(nextTheme);
                });
            }
        })();

        document.addEventListener('DOMContentLoaded', function () {
            var sidebar = document.getElementById('adminSidebar');
            document.getElementById('adminSidebarToggle')?.addEventListener('click', function () {
                sidebar.classList.toggle('collapsed');
            });

            document.querySelectorAll('.stat-tile-value[data-count]').forEach(function (el) {
                var target = parseInt(el.dataset.count, 10) || 0;
                var duration = 700;
                var start = null;

                function tick(timestamp) {
                    if (!start) start = timestamp;
                    var progress = Math.min((timestamp - start) / duration, 1);
                    var eased = 1 - Math.pow(1 - progress, 3);
                    el.textContent = Math.round(eased * target).toLocaleString();
                    if (progress < 1) window.requestAnimationFrame(tick);
                }

                window.requestAnimationFrame(tick);
            });
        });
    </script>

    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @yield('scripts')
</body>
</html>
