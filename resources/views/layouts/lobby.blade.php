<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Project M.A.N.G.R.O.V.E')</title>
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
            position: relative;
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
            padding: 0 40px;
            background: var(--surface);
            border-bottom: 1px solid var(--border-soft);
            box-shadow: 0 1px 3px hsl(var(--shadow-color) / 0.05);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        /* Only populated on pages that fill the navbar-center section --
           absent from the DOM entirely elsewhere (the markup below only
           renders it when that section exists), so it never affects the
           two other pages this layout serves. */
        .navbar-center {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 20px;
            min-width: 0;
            margin: 0 28px;
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

        /* ============ USER MENU (burger) -- tucks the theme toggle,
           account settings, profile, and logout behind one button instead
           of a row of controls always taking up navbar space, matching
           the workspace layout's pattern. ============ */
        .user-cluster {
            position: relative;
        }

        .user-avatar {
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

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-name {
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

        .user-menu-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 240px;
            background: var(--surface);
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            box-shadow: 0 16px 36px hsl(var(--shadow-color) / 0.18);
            padding: 8px;
            z-index: 1300;
        }

        .user-menu-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
        }

        .user-menu-profile-text {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 0;
        }

        .user-menu-divider {
            height: 1px;
            background: var(--border-soft);
            margin: 6px 4px;
        }

        .user-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 9px 10px;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: var(--dark-gray);
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            text-align: left;
            cursor: pointer;
        }

        .user-menu-item i {
            width: 16px;
            color: var(--accent-green);
            font-size: 13px;
            flex-shrink: 0;
        }

        .user-menu-item:hover {
            background: rgba(45, 106, 79, 0.08);
        }

        .user-menu-item-danger:hover {
            background: rgba(180, 35, 24, 0.08);
            color: #B42318;
        }

        .user-menu-item-danger:hover i {
            color: #B42318;
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

        /* Destructive variant -- stacks on top of .btn-primary, same
           red used by the existing archive-button hover state. */
        .btn-primary.btn-danger {
            background: #B42318;
        }
        .btn-primary.btn-danger:hover {
            background: #8f1b13;
        }
        .btn-primary.btn-danger:disabled {
            background: rgba(180, 35, 24, 0.35);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* ============ PAGE WRAPPER ============ */
        /* min-height (not the old flex/margin-top:auto "sticky footer"
           trick) guarantees at least one viewport's worth of content
           before the footer, so on short pages (like a bare form) the
           footer sits below the fold and only appears once the user
           actually scrolls, instead of always being glued to the exact
           bottom edge of the screen. */
        .page-content {
            min-height: calc(100vh - 78px);
            max-width: 1600px;
            width: 100%;
            margin: 0 auto;
            padding: 40px 40px 80px;
            box-sizing: border-box;
        }

        /* Opt-in per page (via the page-content-class section): drops the
           1600px cap and padding so a page can run edge to edge. */
        .page-content.page-content--full {
            max-width: none;
            padding: 0;
        }

        .site-footer {
            background: var(--surface);
            border-top: 1px solid var(--border-soft);
            color: var(--text-muted);
        }

        .site-footer-inner {
            max-width: 1600px;
            margin: 0 auto;
            padding: 32px 40px;
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

        .footer-meta a,
        .footer-meta button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            text-decoration: none;
            background: none;
            border: none;
            padding: 0;
            font: inherit;
            font-size: 13px;
            cursor: pointer;
        }
        .footer-meta a:hover,
        .footer-meta button:hover {
            color: var(--accent-green);
        }

        /* ============ LEGAL MODAL (Terms & Privacy) -- same content/behavior
           as the login page's, restyled with this layout's own tokens
           instead of duplicating its color variables. ============ */
        .legal-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 1600;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(8, 28, 21, 0.55);
        }

        .legal-modal-overlay.is-open {
            display: flex;
        }

        .legal-modal {
            width: min(720px, 100%);
            max-height: min(80vh, 720px);
            display: flex;
            flex-direction: column;
            background: var(--surface);
            border-radius: 12px;
            box-shadow: 0 28px 80px hsl(var(--shadow-color) / 0.3);
            overflow: hidden;
        }

        .legal-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 12px 12px 20px;
            border-bottom: 1px solid var(--border-soft);
        }

        .legal-tabs {
            display: flex;
            gap: 8px;
        }

        .legal-tab {
            border: 0;
            background: none;
            padding: 8px 4px;
            font: inherit;
            font-weight: 700;
            font-size: 14px;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }

        .legal-tab.is-active {
            color: var(--dark-gray);
            border-bottom-color: var(--forest-green);
        }

        .legal-modal-close {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border: 0;
            border-radius: 8px;
            background: var(--off-white);
            color: var(--text-muted);
            cursor: pointer;
        }

        .legal-modal-close:hover {
            background: var(--border-soft);
        }

        .legal-modal-body {
            padding: 20px 24px 28px;
            overflow-y: auto;
        }

        .legal-panel { display: none; }
        .legal-panel.is-active { display: block; }

        .legal-doc-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 12.5px;
            letter-spacing: 0.06em;
            color: var(--accent-green);
            text-transform: uppercase;
        }

        .legal-doc-subtitle {
            font-family: 'Poppins', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 6px;
        }

        .legal-doc-meta {
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 20px;
        }

        .legal-panel h4 {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            color: var(--dark-gray);
            margin: 20px 0 8px;
        }

        .legal-panel p {
            color: var(--dark-gray);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .legal-panel ul {
            margin: 0 0 10px 18px;
            color: var(--dark-gray);
            font-size: 14px;
        }

        .legal-panel li {
            margin-bottom: 6px;
        }

        @media (max-width: 640px) {
            .navbar {
                padding: 0 16px;
            }
            .page-content {
                padding: 28px 16px 60px;
            }
            .page-content.page-content--full {
                padding: 0;
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
    @include('partials.motion-styles')
</head>
<body>
    @include('partials.page-transition')
    @include('partials.logout-modal')

    <nav class="navbar">
        <div class="navbar-brand">
            <div class="leaf-icon">
                <img src="{{ asset('images/logo/ProjMangrove_Logo.svg') }}" alt="Project M.A.N.G.R.O.V.E logo">
            </div>
            <div class="brand-text">
                <span class="brand-name">Project M.A.N.G.R.O.V.E</span>
                @hasSection('navbar-subtitle')
                    <span class="brand-sub">@yield('navbar-subtitle')</span>
                @endif
            </div>
        </div>

        {{-- Empty on every page except the lobby, which fills this with its
             greeting/search/quick-actions -- kept out of the shared markup
             above so projects.create/projects.show (the layout's other two
             consumers) don't inherit lobby-specific controls. --}}
        @hasSection('navbar-center')
            <div class="navbar-center">
                @yield('navbar-center')
            </div>
        @endif

        <div class="navbar-right">
            @include('partials.notification-bell')

            <div class="user-cluster">
                <button class="icon-btn" id="userMenuToggle" type="button" title="Menu" aria-haspopup="true" aria-expanded="false">
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="user-menu-dropdown" id="userMenuDropdown" hidden>
                    <div class="user-menu-profile">
                        <div class="user-avatar">
                            @if ($user->avatar_url ?? null)
                                <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}">
                            @else
                                {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                            @endif
                        </div>
                        <div class="user-menu-profile-text">
                            <span class="user-name">{{ $user->name ?? 'Guest User' }}</span>
                            <span class="role-badge">{{ $user->role?->name ? ucfirst(str_replace('_', ' ', $user->role->name)) : 'Viewer' }}</span>
                        </div>
                    </div>

                    <div class="user-menu-divider"></div>

                    <button class="user-menu-item" id="themeToggle" type="button">
                        <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
                        <span id="themeToggleLabel">Switch to dark mode</span>
                    </button>
                    <a href="{{ route('settings.account') }}" class="user-menu-item">
                        <i class="fa-solid fa-gear"></i>
                        <span>Account Settings</span>
                    </a>

                    <div class="user-menu-divider"></div>

                    <form action="{{ url('/logout') }}" method="POST" style="margin:0;" data-loading-label="Logging out...">
                        @csrf
                        <button type="submit" class="user-menu-item user-menu-item-danger" onclick="return openLogoutModal(event)">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                            <span>Log out</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    @include('partials.password-warning-banner')

    <div class="page-content @yield('page-content-class')">
        @yield('content')
    </div>

    @include('partials.app-footer')

    <script>
        (function () {
            var toggle = document.getElementById('userMenuToggle');
            var dropdown = document.getElementById('userMenuDropdown');
            if (!toggle || !dropdown) return;

            function closeMenu() {
                dropdown.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            }

            function openMenu() {
                dropdown.hidden = false;
                toggle.setAttribute('aria-expanded', 'true');
            }

            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                if (dropdown.hidden) openMenu(); else closeMenu();
            });

            // The theme toggle and settings link live inside the dropdown,
            // so a click on either would otherwise also trigger the
            // outside-click handler below on its way up -- stopping
            // propagation here keeps the menu open through those
            // interactions except ones that navigate/submit away
            // (settings link, logout) where staying open doesn't matter.
            dropdown.addEventListener('click', function (event) {
                event.stopPropagation();
            });

            document.addEventListener('click', closeMenu);
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') closeMenu();
            });
        })();
    </script>

    <script>
        (function () {
            var legalModal = document.getElementById('legalModal');
            if (!legalModal) return;

            function showLegalTab(tabName) {
                legalModal.querySelectorAll('[data-legal-tab]').forEach(function (tab) {
                    tab.classList.toggle('is-active', tab.dataset.legalTab === tabName);
                });
                legalModal.querySelectorAll('[data-legal-panel]').forEach(function (panel) {
                    panel.classList.toggle('is-active', panel.dataset.legalPanel === tabName);
                });
            }

            function closeLegalModal() {
                legalModal.classList.remove('is-open');
                legalModal.setAttribute('aria-hidden', 'true');
            }

            document.querySelectorAll('[data-legal-open]').forEach(function (button) {
                button.addEventListener('click', function () {
                    showLegalTab(button.dataset.legalOpen);
                    legalModal.classList.add('is-open');
                    legalModal.setAttribute('aria-hidden', 'false');
                });
            });

            legalModal.querySelectorAll('[data-legal-tab]').forEach(function (tab) {
                tab.addEventListener('click', function () { showLegalTab(tab.dataset.legalTab); });
            });

            legalModal.querySelector('[data-legal-close]').addEventListener('click', closeLegalModal);
            legalModal.addEventListener('click', function (event) {
                if (event.target === legalModal) closeLegalModal();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && legalModal.classList.contains('is-open')) closeLegalModal();
            });
        })();
    </script>

    <script>
        (function () {
            var toggleBtn = document.getElementById('themeToggle');
            var toggleIcon = document.getElementById('themeToggleIcon');
            var toggleLabel = document.getElementById('themeToggleLabel');

            function applyIcon(theme) {
                if (toggleIcon) toggleIcon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
                // Labeled by the theme a click would switch TO, not the
                // current one -- "Switch to light mode" while dark is
                // active, matching the sun/moon icon's own meaning.
                if (toggleLabel) toggleLabel.textContent = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
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
