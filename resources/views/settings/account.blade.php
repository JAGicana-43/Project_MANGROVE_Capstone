<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Project M.A.N.G.R.O.V.E</title>
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        :root {
            --forest-green: #2D6A4F;
            --sage-green: #74C69D;
            --teal: #40916C;
            --off-white: #F8F9FA;
            --dark-gray: #212529;
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
            --text-muted: #9FB0A8;
            --shadow-color: 220 40% 4%;
            --surface: #1C2622;
            --heading-accent: #DEE6E2;
            --accent-green: #74C69D;
        }

        * , *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'Inter', sans-serif;
            background: var(--off-white);
            color: var(--dark-gray);
        }

        .navbar, .page-content, .site-footer {
            position: relative;
            z-index: 1;
        }

        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 32px;
            background: var(--surface);
            border-bottom: 1px solid var(--border-soft);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .leaf-icon img { width: 40px; height: 40px; display: block; }

        .brand-text { display: flex; flex-direction: column; line-height: 1.25; }
        .brand-name { font-family: 'Poppins', sans-serif; font-weight: 700; color: var(--heading-accent); font-size: 15px; }
        .brand-sub { font-size: 11px; color: var(--text-muted); letter-spacing: 0.03em; }

        .navbar-right { display: flex; align-items: center; gap: 12px; }

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
            text-decoration: none;
            box-shadow: 0 6px 16px hsl(var(--shadow-color) / 0.08);
            transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease;
        }

        .icon-btn:hover {
            background: var(--forest-green);
            color: #fff;
            transform: translateY(-1px);
        }

        .navbar-user { display: flex; align-items: center; gap: 10px; }

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

        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .user-meta { display: flex; flex-direction: column; line-height: 1.2; }
        .user-name { font-weight: 600; font-size: 14px; color: var(--dark-gray); }

        .role-badge {
            display: inline-block;
            width: fit-content;
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            padding: 3px 9px;
            border-radius: 999px;
            background: rgba(116, 198, 157, 0.2);
            color: var(--accent-green);
        }

        .btn-logout {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(180, 35, 24, 0.25);
            background: transparent;
            color: #B42318;
            padding: 9px 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
        }

        .btn-logout:hover { background: rgba(180, 35, 24, 0.08); }

        .page-content {
            flex: 1;
            padding: 40px 24px;
        }

        .settings-shell {
            max-width: 640px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .settings-header a.back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 14px;
        }

        .settings-header a.back-link:hover { color: var(--accent-green); }

        .settings-header h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 24px;
            color: var(--heading-accent);
        }

        .settings-header p {
            color: var(--text-muted);
            margin-top: 6px;
            font-size: 14px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
        }

        .alert-success {
            background: rgba(45, 106, 79, 0.1);
            color: var(--forest-green);
            border: 1px solid rgba(45, 106, 79, 0.22);
        }

        .settings-card {
            background: var(--surface);
            border: 1px solid var(--border-soft);
            border-radius: 16px;
            padding: 24px 26px;
            box-shadow: 0 14px 36px hsl(var(--shadow-color) / 0.08);
        }

        .settings-card h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            color: var(--heading-accent);
            margin-bottom: 18px;
        }

        .card-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            font: inherit;
            text-align: left;
        }

        .card-toggle-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            font-weight: 600;
            color: var(--heading-accent);
        }

        .card-toggle-title i { color: var(--accent-green); }

        .card-toggle-chevron {
            color: var(--text-muted);
            transition: transform 0.18s ease;
        }

        .collapsible .card-body {
            margin-top: 18px;
        }

        .collapsible.collapsed .card-toggle {
            padding-bottom: 0;
        }

        .collapsible.collapsed .card-body {
            display: none;
        }

        .collapsible.collapsed .card-toggle-chevron {
            transform: rotate(-90deg);
        }

        .security-hint {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 16px;
        }

        .profile-row {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .profile-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--sage-green);
            color: var(--forest-green-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 22px;
            font-family: 'Poppins', sans-serif;
            overflow: hidden;
            flex: 0 0 auto;
        }

        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .profile-name { font-weight: 700; font-size: 16px; color: var(--dark-gray); }
        .profile-email { font-size: 13.5px; color: var(--text-muted); margin: 3px 0 8px; }

        form { display: flex; flex-direction: column; gap: 14px; }

        label {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-muted);
        }

        input[type="password"],
        input[type="text"].password-input {
            height: 42px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1px solid var(--border-soft);
            font: inherit;
            font-size: 13.5px;
            color: var(--dark-gray);
            background: var(--surface);
        }

        input[type="password"]:focus,
        input[type="text"].password-input:focus {
            outline: 2px solid rgba(116, 198, 157, 0.45);
            border-color: var(--forest-green);
        }

        .password-field {
            position: relative;
            display: flex;
        }

        .password-field input {
            width: 100%;
            padding-right: 40px;
        }

        .password-peek {
            position: absolute;
            top: 0;
            right: 0;
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: none;
            color: var(--text-muted);
            cursor: pointer;
        }

        .password-peek:hover { color: var(--forest-green); }

        .field-error {
            color: #C0392B;
            font-size: 12px;
            font-weight: 600;
        }

        .password-strength {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: -6px;
        }

        .password-strength-bar {
            flex: 1;
            height: 6px;
            border-radius: 999px;
            background: rgba(108, 117, 125, 0.18);
            overflow: hidden;
        }

        .password-strength-fill {
            height: 100%;
            width: 0%;
            border-radius: 999px;
            background: #C0392B;
            transition: width 0.2s ease, background 0.2s ease;
        }

        .password-strength-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            white-space: nowrap;
        }

        .password-requirements {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-top: -6px;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .password-requirements li {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .password-requirements li i {
            font-size: 6px;
            color: rgba(108, 117, 125, 0.5);
        }

        .password-requirements li.met {
            color: var(--forest-green);
        }

        .password-requirements li.met i {
            font-size: 12px;
            color: var(--forest-green);
        }

        .password-requirements li.met i::before {
            content: "\f00c";
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--forest-green);
            color: #fff;
            border: none;
            padding: 11px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            align-self: flex-start;
        }

        .btn-primary:hover { background: var(--forest-green-dark); }

        .site-footer {
            margin-top: auto;
            background: var(--surface);
            border-top: 1px solid var(--border-soft);
        }

        .site-footer-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .footer-brand { display: flex; align-items: center; gap: 12px; }

        .footer-mark {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: rgba(45, 106, 79, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6px;
        }

        .footer-mark img { width: 100%; height: 100%; object-fit: contain; display: block; }

        .footer-brand strong {
            display: block;
            color: var(--heading-accent);
            font-family: 'Poppins', sans-serif;
            font-size: 13.5px;
        }

        .footer-brand span { display: block; font-size: 12px; color: var(--text-muted); }

        .footer-meta { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; font-size: 12.5px; }
        .footer-meta a { color: var(--text-muted); text-decoration: none; }
        .footer-meta a:hover { color: var(--accent-green); }

        @media (max-width: 640px) {
            .navbar { padding: 14px 16px; flex-wrap: wrap; }
            .user-meta { display: none; }
            .page-content { padding: 28px 14px; }
            .settings-card { padding: 20px 18px; }
            .site-footer-inner { padding: 22px 16px; }
        }
    </style>
    @include('partials.page-transition-styles')
</head>
<body>
    @include('partials.page-transition')
    @include('partials.logout-modal')
    @include('partials.password-warning-banner')

    <nav class="navbar">
        <div class="navbar-brand">
            <div class="leaf-icon">
                <img src="{{ asset('images/logo/ProjMangrove_Logo.svg') }}" alt="Project M.A.N.G.R.O.V.E logo">
            </div>
            <div class="brand-text">
                <span class="brand-name">Project M.A.N.G.R.O.V.E</span>
                
            </div>
        </div>

        <div class="navbar-right">
            <a href="{{ $backRoute }}" class="icon-btn" title="Back to Dashboard">
                <i class="fa-solid fa-arrow-left"></i>
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

    <div class="page-content">
        <div class="settings-shell">
            <div class="settings-header">
                <a href="{{ $backRoute }}" class="back-link">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </a>
                <h1>Account Settings</h1>
                <p>Manage your account details and security.</p>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="settings-card">
                <h2>Profile</h2>
                <div class="profile-row">
                    <div class="profile-avatar">
                        @if ($user->avatar_url ?? null)
                            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}">
                        @else
                            {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                        @endif
                    </div>
                    <div>
                        <div class="profile-name">{{ $user->name }}</div>
                        <div class="profile-email">{{ $user->email }}</div>
                        <span class="role-badge">{{ $user->role?->name ? ucfirst(str_replace('_', ' ', $user->role->name)) : 'Viewer' }}</span>
                    </div>
                </div>
            </div>

            @php
                // Keep the password form out of sight by default — only
                // auto-expand it if there's something from a previous
                // submission the user needs to see (a validation error or a
                // just-saved confirmation), or if the password change is
                // required, so it doesn't get silently lost behind the
                // collapsed section.
                $securityOpen = $errors->any() || session('success') || $user->must_change_password;
            @endphp
            <div class="settings-card collapsible {{ $securityOpen ? '' : 'collapsed' }}" id="securityCard">
                <button type="button" class="card-toggle" id="securityToggleBtn" aria-expanded="{{ $securityOpen ? 'true' : 'false' }}" aria-controls="securityBody">
                    <span class="card-toggle-title">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Account Security</span>
                    </span>
                    <i class="fa-solid fa-chevron-down card-toggle-chevron"></i>
                </button>
                <div class="card-body" id="securityBody">
                    <p class="security-hint">Change the password used to sign in to your account.</p>
                    <form method="POST" action="{{ route('settings.account.password') }}" data-loading-label="Updating password...">
                        @csrf
                        @method('PUT')

                        <label>Current Password
                            <div class="password-field">
                                <input type="password" name="current_password" autocomplete="current-password" required>
                                <button type="button" class="password-peek" aria-label="Show password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </label>
                        @error('current_password')
                            <p class="field-error">{{ $message }}</p>
                        @enderror

                        <label>New Password
                            <div class="password-field">
                                <input type="password" name="password" id="newPassword" autocomplete="new-password" minlength="8" required>
                                <button type="button" class="password-peek" aria-label="Show password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </label>
                        <div class="password-strength" id="passwordStrength" hidden>
                            <div class="password-strength-bar">
                                <div class="password-strength-fill" id="passwordStrengthFill"></div>
                            </div>
                            <span class="password-strength-label" id="passwordStrengthLabel"></span>
                        </div>
                        <ul class="password-requirements" id="passwordRequirements">
                            <li data-rule="length"><i class="fa-solid fa-circle"></i> At least 8 characters</li>
                            <li data-rule="case"><i class="fa-solid fa-circle"></i> Upper and lowercase letters</li>
                            <li data-rule="number"><i class="fa-solid fa-circle"></i> At least one number</li>
                            <li data-rule="symbol"><i class="fa-solid fa-circle"></i> At least one symbol (optional, adds strength)</li>
                        </ul>
                        @error('password')
                            <p class="field-error">{{ $message }}</p>
                        @enderror

                        <label>Confirm New Password
                            <div class="password-field">
                                <input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
                                <button type="button" class="password-peek" aria-label="Show password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </label>

                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-key"></i>
                            <span>Update Password</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @include('partials.site-footer')

    <script>
        (function () {
            var toggleBtn = document.getElementById('securityToggleBtn');
            var card = document.getElementById('securityCard');
            if (!toggleBtn || !card) return;

            toggleBtn.addEventListener('click', function () {
                var isCollapsed = card.classList.toggle('collapsed');
                toggleBtn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
            });
        })();

        (function () {
            document.querySelectorAll('.password-peek').forEach(function (btn) {
                var input = btn.previousElementSibling;
                var icon = btn.querySelector('i');
                if (!input) return;

                btn.addEventListener('click', function () {
                    var isHidden = input.type === 'password';
                    input.type = isHidden ? 'text' : 'password';
                    input.classList.toggle('password-input', isHidden);
                    icon.classList.toggle('fa-eye', !isHidden);
                    icon.classList.toggle('fa-eye-slash', isHidden);
                    btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                });
            });
        })();

        (function () {
            var input = document.getElementById('newPassword');
            var strengthWrap = document.getElementById('passwordStrength');
            var fill = document.getElementById('passwordStrengthFill');
            var label = document.getElementById('passwordStrengthLabel');
            var requirements = document.getElementById('passwordRequirements');
            if (!input || !strengthWrap || !fill || !label || !requirements) return;

            var levels = [
                { min: 0, text: 'Very weak', color: '#C0392B', width: '15%' },
                { min: 1, text: 'Weak', color: '#E67E22', width: '35%' },
                { min: 2, text: 'Fair', color: '#F0A500', width: '55%' },
                { min: 3, text: 'Good', color: '#74C69D', width: '80%' },
                { min: 4, text: 'Strong', color: '#2D6A4F', width: '100%' },
            ];

            input.addEventListener('input', function () {
                var value = input.value;
                strengthWrap.hidden = value.length === 0;
                if (value.length === 0) return;

                var rules = {
                    length: value.length >= 8,
                    case: /[a-z]/.test(value) && /[A-Z]/.test(value),
                    number: /[0-9]/.test(value),
                    symbol: /[^A-Za-z0-9]/.test(value),
                };

                requirements.querySelectorAll('li').forEach(function (item) {
                    var rule = item.getAttribute('data-rule');
                    item.classList.toggle('met', !!rules[rule]);
                });

                var score = [rules.length, rules.case, rules.number, rules.symbol].filter(Boolean).length;
                var level = levels.slice().reverse().find(function (l) { return score >= l.min; }) || levels[0];

                fill.style.width = level.width;
                fill.style.background = level.color;
                label.textContent = level.text;
                label.style.color = level.color;
            });
        })();
    </script>
</body>
</html>
