<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Project MANGROVE')</title>
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

    <!-- Google Fonts: Poppins (headings) + Inter (body) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        :root {
            --forest-green: #2D6A4F;
            --sage-green: #74C69D;
            --teal: #40916C;
            --off-white: #F8F9FA;
            --dark-gray: #212529;

            /* derived tones for depth without adding new hues */
            --forest-green-dark: #1B4332;
            --border-soft: #E4E8E6;
            --text-muted: #6C757D;
            --shadow-color: 220 20% 30%;
            --surface: #FFFFFF;
            --heading-accent: var(--forest-green-dark);
            --accent-green: var(--forest-green);
        }

        [data-theme="dark"] {
            --off-white: #0F1613;
            --dark-gray: #DEE6E2;
            --border-soft: rgba(222, 230, 226, 0.14);
            --text-muted: #9AA8A2;
            --shadow-color: 0 0% 0%;
            --surface: #1C2622;
            --heading-accent: #6FCF97;
            --accent-green: #6FCF97;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--off-white);
            color: var(--dark-gray);
            min-height: 100vh;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        /* Quiet topographic contour pattern — subtle nod to elevation/GIS data.
           Fixed, low-opacity, never competes with content. */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            opacity: 0.045;
            background-image:
                repeating-radial-gradient(circle at 12% 8%, transparent 0, transparent 26px, var(--forest-green) 27px, transparent 28px),
                repeating-radial-gradient(circle at 88% 92%, transparent 0, transparent 34px, var(--teal) 35px, transparent 36px);
            background-size: 480px 480px, 620px 620px;
        }

        .navbar, .page-content, .site-footer {
            position: relative;
            z-index: 1;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button {
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            border: none;
        }

        /* ============ NAVBAR ============ */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 78px;
            padding: 0 32px;
            background: var(--surface);
            border-bottom: 1px solid var(--border-soft);
            box-shadow: 0 1px 3px hsl(var(--shadow-color) / 0.05);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .navbar-brand .leaf-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 62px;
            height: 62px;
            border-radius: 50%;
            background: #fff;
            border: 1px solid var(--border-soft);
            padding: 5px;
            box-sizing: border-box;
            flex-shrink: 0;
        }
        .navbar-brand .leaf-icon img { width: 100%; height: 100%; object-fit: contain; display: block; }

        .navbar-brand .brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.15;
        }

        .navbar-brand .brand-text .brand-name {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 16px;
            color: var(--heading-accent);
        }

        .navbar-brand .brand-text .brand-sub {
            font-size: 11px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-user .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--sage-green);
            color: var(--forest-green-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            overflow: hidden;
            flex: 0 0 auto;
        }

        .navbar-user .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .navbar-user .user-meta {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .navbar-user .user-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--dark-gray);
        }

        .role-badge {
            display: inline-block;
            width: fit-content;
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            padding: 1px 8px;
            border-radius: 20px;
            background: rgba(64, 145, 108, 0.12);
            color: var(--teal);
            margin-top: 2px;
        }

        .btn-logout {
            display: flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: 1px solid var(--border-soft);
            color: var(--text-muted);
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.15s ease;
        }

        .btn-logout:hover {
            background: #FDEEEE;
            border-color: #F3C9C9;
            color: #C0392B;
        }

        .icon-btn {
            width: 38px;
            height: 38px;
            border: 1px solid rgba(45, 106, 79, 0.18);
            border-radius: 12px;
            background: var(--surface);
            color: var(--accent-green);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 6px 16px hsl(var(--shadow-color) / 0.08);
            transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease;
        }

        .icon-btn:hover {
            background: var(--forest-green);
            color: #fff;
            transform: translateY(-1px);
        }

        /* ============ SHARED BUTTONS ============ */
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--forest-green);
            color: #fff;
            padding: 11px 20px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.15s ease;
            box-shadow: 0 2px 6px hsl(var(--shadow-color) / 0.15);
        }

        .btn-primary:hover {
            background: var(--forest-green-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 10px hsl(var(--shadow-color) / 0.2);
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--surface);
            color: var(--accent-green);
            border: 1px solid var(--sage-green);
            padding: 10px 18px;
            border-radius: 9px;
            font-size: 13.5px;
            font-weight: 600;
            transition: all 0.15s ease;
        }

        .btn-secondary:hover {
            background: rgba(116, 198, 157, 0.12);
        }

        /* ============ PAGE WRAPPER ============ */
        .page-content {
            flex: 1;
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            padding: 40px 32px 80px;
        }

        .site-footer {
            margin-top: auto;
            background: var(--surface);
            border-top: 1px solid var(--border-soft);
            color: var(--text-muted);
        }

        .site-footer-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
            font-size: 13px;
        }

        .footer-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .footer-mark {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #fff;
            padding: 5px;
            box-sizing: border-box;
            flex-shrink: 0;
        }
        .footer-mark img { width: 100%; height: 100%; object-fit: contain; display: block; }

        .footer-brand strong {
            display: block;
            color: var(--heading-accent);
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .footer-brand span {
            display: block;
        }

        .footer-meta {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .footer-meta a { color: var(--text-muted); text-decoration: none; }
        .footer-meta a:hover { color: var(--accent-green); }

        @media (max-width: 640px) {
            .navbar {
                padding: 0 16px;
            }
            .navbar-user .user-meta {
                display: none;
            }
            .page-content {
                padding: 28px 16px 60px;
            }
            .site-footer-inner {
                padding: 22px 16px;
            }
            .footer-meta {
                justify-content: flex-start;
            }
        }
    </style>
    @include('partials.page-transition-styles')
</head>
<body>
    @include('partials.page-transition')
    @include('partials.logout-modal')

    <nav class="navbar">
        <div class="navbar-brand">
            <div class="leaf-icon">
                <img src="{{ asset('images/logo/ProjMangrove_Logo.svg') }}" alt="Project MANGROVE logo">
            </div>
            <div class="brand-text">
                <span class="brand-name">Project MANGROVE</span>
               
            </div>
        </div>

        <div class="navbar-right">
            <button type="button" class="icon-btn" id="themeToggle" title="Toggle dark mode">
                <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
            </button>
            <a href="{{ route('settings.account') }}" class="icon-btn" title="Account Settings">
                <i class="fa-solid fa-gear"></i>
            </a>
            <div class="navbar-user">
                <div class="user-avatar">
                    @if ($user->avatar_url ?? null)
                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}">
                    @else
                        {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                    @endif
                </div>
                <div class="user-meta">
                    <span class="user-name">{{ $user->name ?? 'Guest User' }}</span>
                    <span class="role-badge">{{ $user->role?->name ? ucfirst(str_replace('_', ' ', $user->role->name)) : 'Viewer' }}</span>
                </div>
            </div>
            <form action="{{ url('/logout') }}" method="POST" data-loading-label="Logging out...">
                @csrf
                <button type="submit" class="btn-logout" onclick="return openLogoutModal(event)">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span>Log out</span>
                </button>
            </form>
        </div>
    </nav>

    @include('partials.password-warning-banner')

    <div class="page-content">
        @yield('content')
    </div>

    @include('partials.site-footer')

    <script>
        (function () {
            var toggleBtn = document.getElementById('themeToggle');
            var toggleIcon = document.getElementById('themeToggleIcon');

            function applyIcon(theme) {
                if (!toggleIcon) return;
                toggleIcon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
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
    </script>

    <script>
        window.addEventListener('pageshow', function (event) {
            const navEntry = window.performance?.getEntriesByType?.('navigation')[0];
            const isBackForward = event.persisted || (navEntry && navEntry.type === 'back_forward');

            if (isBackForward) {
                window.location.replace('{{ route("login") }}');
            }
        });
    </script>

    @yield('scripts')
</body>
</html>
