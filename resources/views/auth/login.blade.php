<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project MANGROVE - Login</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/favicon-badge.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo/favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo/apple-touch-icon.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            /* 60% dominant -- white / near-white surfaces */
            --color-canvas: #F7F8F7;
            --color-surface: #FFFFFF;

            /* 30% secondary -- neutral text, borders, muted UI */
            --color-text: #1C2620;
            --color-text-muted: #66766D;
            --color-border: #E1E6E2;

            /* 10% accent -- green only, reserved for CTAs, links, focus, active states */
            --color-accent: #2F8F68;
            --color-accent-soft: #9FD8B8;
            --color-accent-contrast: #082016;

            /* functional status colors, kept outside the 60-30-10 ratio */
            --color-danger: #B42318;
            --color-warning: #9A5B00;
            --color-success: #216346;
        }

        body {
            min-height: 100vh;
            font-family: "Inter", sans-serif;
            color: var(--color-text);
            background: var(--color-canvas);
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
        }

        .auth-page {
            flex: 1;
            width: 100%;
            display: grid;
            place-items: center;
        }

        .login-shell {
            width: min(1080px, 100%);
            min-height: 680px;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            border-radius: 8px;
            overflow: hidden;
            background: var(--color-surface);
            box-shadow: 0 28px 80px rgba(12, 38, 29, 0.16);
            border: 1px solid rgba(11, 38, 29, 0.08);
        }

        .visual {
            position: relative;
            color: var(--color-surface);
            padding: 2rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background:
                linear-gradient(180deg, rgba(8, 28, 21, 0.28), rgba(8, 28, 21, 0.88)),
                url("{{ asset('images/landing/mangrove-forest-philippines.jpg') }}") center / cover;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .brand-mark {
            width: 64px;
            height: 64px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #fff;
            padding: 5px;
            box-sizing: border-box;
            flex-shrink: 0;
        }
        .brand-mark img { width: 100%; height: 100%; object-fit: contain; display: block; }

        .visual-copy {
            max-width: 470px;
        }

        .visual-copy small {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: var(--color-accent-soft);
            font-weight: 800;
            letter-spacing: 0.11em;
            text-transform: uppercase;
            margin-bottom: 0.9rem;
        }

        .visual-copy h1 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(2rem, 5vw, 3.6rem);
            line-height: 1.02;
            letter-spacing: 0;
            margin-bottom: 1rem;
        }

        .visual-copy p {
            color: rgba(255, 255, 255, 0.74);
            font-size: 1rem;
        }

        .form-side {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(1.5rem, 5vw, 3.5rem);
        }

        .form-wrap {
            width: min(410px, 100%);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: var(--color-accent);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 2rem;
        }

        h2 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(1.85rem, 4vw, 2.4rem);
            line-height: 1.08;
            margin-bottom: 0.55rem;
        }

        .subtitle {
            color: var(--color-text-muted);
            margin-bottom: 1.6rem;
        }

        .alert {
            border-radius: 8px;
            padding: 0.85rem 0.95rem;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
        }

        .alert.error {
            color: var(--color-danger);
            background: #fff1ef;
            border-color: #ffd2cc;
        }

        .alert.locked {
            color: var(--color-warning);
            background: #fff6df;
            border-color: #f8dd9a;
        }

        .alert.success {
            color: var(--color-success);
            background: #eef7f1;
            border-color: #cfe8d7;
        }

        label {
            display: block;
            font-size: 0.84rem;
            font-weight: 800;
            color: var(--color-text);
            margin-bottom: 0.45rem;
        }

        .field {
            position: relative;
            margin-bottom: 1rem;
        }

        .field i {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #8b9a90;
        }

        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            min-height: 48px;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            padding: 0.78rem 0.9rem 0.78rem 2.55rem;
            color: var(--color-text);
            background: var(--color-canvas);
            font: inherit;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .password-field input {
            padding-right: 3rem;
        }

        .password-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .password-label-row label {
            margin-bottom: 0;
        }

        .forgot-password-link {
            color: var(--color-accent);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 700;
            margin-bottom: 0.45rem;
        }

        .forgot-password-link:hover {
            text-decoration: underline;
        }

        .peek-password {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            width: 38px;
            min-height: 38px;
            margin: 0;
            padding: 0;
            border-radius: 8px;
            color: var(--color-text-muted);
            background: transparent;
            box-shadow: none;
        }

        .peek-password:hover {
            transform: translateY(-50%);
            background: #eef5ef;
        }

        .peek-password i {
            position: static;
            transform: none;
            color: inherit;
        }

        input:focus {
            outline: none;
            border-color: var(--color-accent);
            background: var(--color-surface);
            box-shadow: 0 0 0 4px rgba(47, 143, 104, 0.14);
        }

        button {
            width: 100%;
            min-height: 50px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            border: 0;
            border-radius: 8px;
            color: var(--color-accent-contrast);
            background: var(--color-accent-soft);
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            margin-top: 0.45rem;
            transition: transform 0.2s ease, background 0.2s ease;
        }

        button:hover {
            transform: translateY(-2px);
            background: #8fd0aa;
        }

        .security-note {
            display: flex;
            gap: 0.65rem;
            align-items: flex-start;
            color: var(--color-text-muted);
            font-size: 0.85rem;
            margin-top: 1.25rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--color-border);
        }

        .security-note i {
            color: var(--color-accent);
            margin-top: 0.15rem;
        }

        .legal-links {
            color: var(--color-text-muted);
            font-size: 0.85rem;
            margin-top: 0.75rem;
            text-align: center;
        }

        .legal-links button {
            display: inline;
            width: auto;
            min-height: 0;
            border: 0;
            background: none;
            padding: 0;
            margin: 0;
            color: var(--color-accent);
            font: inherit;
            font-weight: 700;
            text-decoration: underline;
            cursor: pointer;
        }

        .legal-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
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
            background: var(--color-surface);
            border-radius: 8px;
            box-shadow: 0 28px 80px rgba(12, 38, 29, 0.28);
            overflow: hidden;
        }

        .legal-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.75rem 0.75rem 0.75rem 1.25rem;
            border-bottom: 1px solid var(--color-border);
        }

        .legal-tabs {
            display: flex;
            gap: 0.5rem;
        }

        .legal-tab {
            border: 0;
            background: none;
            padding: 0.5rem 0.25rem;
            font: inherit;
            font-weight: 800;
            font-size: 0.9rem;
            color: var(--color-text-muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }

        .legal-tab.is-active {
            color: var(--color-text);
            border-bottom-color: var(--color-accent);
        }

        .legal-modal-close {
            width: 34px;
            min-height: 34px;
            display: grid;
            place-items: center;
            border: 0;
            border-radius: 8px;
            background: var(--color-canvas);
            color: var(--color-text-muted);
            cursor: pointer;
            margin: 0;
        }

        .legal-modal-close:hover {
            background: var(--color-border);
            transform: none;
        }

        .legal-modal-body {
            padding: 1.25rem 1.5rem 1.75rem;
            overflow-y: auto;
        }

        .legal-panel { display: none; }
        .legal-panel.is-active { display: block; }

        .legal-doc-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 0.8rem;
            letter-spacing: 0.06em;
            color: var(--color-accent);
            text-transform: uppercase;
        }

        .legal-doc-subtitle {
            font-family: 'Poppins', sans-serif;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
        }

        .legal-doc-meta {
            color: var(--color-text-muted);
            font-size: 0.82rem;
            margin-bottom: 1.25rem;
        }

        .legal-panel h4 {
            font-family: 'Poppins', sans-serif;
            font-size: 0.98rem;
            margin: 1.25rem 0 0.5rem;
        }

        .legal-panel p {
            color: var(--color-text);
            font-size: 0.9rem;
            margin-bottom: 0.6rem;
        }

        .legal-panel ul {
            margin: 0 0 0.6rem 1.1rem;
            color: var(--color-text);
            font-size: 0.9rem;
        }

        .legal-panel li {
            margin-bottom: 0.35rem;
        }

        .create-account {
            margin-top: 1.1rem;
            color: var(--color-text-muted);
            font-size: 0.9rem;
            text-align: center;
        }

        .create-account a {
            color: var(--color-accent);
            font-weight: 800;
            text-decoration: none;
        }

        .create-account a:hover {
            text-decoration: underline;
        }

        .site-footer {
            width: min(1080px, 100%);
            margin: 1.25rem auto 0;
            color: var(--color-text-muted);
            font-size: 0.82rem;
        }

        .site-footer-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            padding: 1rem 0 0;
            border-top: 1px solid rgba(11, 38, 29, 0.12);
        }

        .footer-brand {
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }

        .footer-mark {
            width: 52px;
            height: 52px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: rgba(47, 143, 104, 0.1);
            padding: 5px;
            box-sizing: border-box;
            flex-shrink: 0;
        }
        .footer-mark img { width: 100%; height: 100%; object-fit: contain; display: block; }

        .footer-brand strong {
            display: block;
            color: var(--color-text);
            font-size: 0.9rem;
        }

        .footer-brand span {
            display: block;
        }

        .footer-meta {
            display: flex;
            gap: 0.9rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .footer-meta a { color: var(--color-text-muted); text-decoration: none; }
        .footer-meta a:hover { color: var(--color-accent); }

        @media (max-width: 860px) {
            body { padding: 0; }
            .auth-page { place-items: stretch; }
            .login-shell {
                min-height: 100vh;
                grid-template-columns: 1fr;
                border-radius: 0;
            }
            .visual {
                min-height: 310px;
                padding: 1.25rem;
            }
            .form-side {
                align-items: flex-start;
                padding: 2rem 1.25rem;
            }
            .site-footer {
                width: 100%;
                margin: 0;
                padding: 0 1.25rem 1.25rem;
                background: var(--color-surface);
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
    <div class="auth-page">
        <main class="login-shell">
            <section class="visual" aria-label="Project MANGROVE field image">
                <div class="brand">
                    <span class="brand-mark"><img src="{{ asset('images/logo/ProjMangrove_Logo.svg') }}" alt="Project MANGROVE logo"></span>
                    <span>Project MANGROVE</span>
                </div>

                <div class="visual-copy">
                    <small><i class="fa-solid fa-shield-halved"></i> Secure project access</small>
                    <h1>Log in to continue field monitoring.</h1>
                    <p>Review UAV survey outputs, soil readings, suitability surfaces, and rehabilitation reports from one coordinated workspace.</p>
                </div>
            </section>

            <section class="form-side">
                <div class="form-wrap">
                    <a href="{{ route('landing') }}" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to landing page</a>

                <h2>{{ ($isFirstVisit ?? false) ? 'Welcome to Project MANGROVE' : 'Welcome back' }}</h2>
                <p class="subtitle">
                    @if($isFirstVisit ?? false)
                        Log in with your assigned account to start monitoring your mangrove rehabilitation sites.
                    @else
                        Use your assigned account to access the monitoring dashboard.
                    @endif
                </p>

                @if(session('error'))
                    <div class="alert error">{{ session('error') }}</div>
                @endif

                @if(session('locked'))
                    <div class="alert locked">{{ session('locked') }}</div>
                @endif

                @if(session('success'))
                    <div class="alert success">{{ session('success') }}</div>
                @endif

                @if($errors->any())
                    <div class="alert error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login.post') }}" data-loading-label="Logging in...">
                    @csrf

                    <label for="email">Email address</label>
                    <div class="field">
                        <i class="fa-solid fa-envelope"></i>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            required
                            autofocus
                        >
                    </div>

                    <div class="password-label-row">
                        <label for="password">Password</label>
                        <a href="{{ route('password.request') }}" class="forgot-password-link">Forgot password?</a>
                    </div>
                    <div class="field password-field">
                        <i class="fa-solid fa-key"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="peek-password" data-toggle-password="password" aria-label="Show password" aria-pressed="false">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>

                    <button type="submit"><i class="fa-solid fa-right-to-bracket"></i> Log in</button>
                </form>

                <div class="security-note">
                    <i class="fa-solid fa-circle-info"></i>
                    <p>Access is limited to administrators, researchers, and field technicians assigned to Project MANGROVE.</p>
                </div>

                <div class="legal-links">
                    By logging in, you agree to the
                    <button type="button" data-legal-open="terms">Terms and Conditions</button>
                    and
                    <button type="button" data-legal-open="privacy">Privacy Policy</button>.
                </div>
                </div>
            </section>
        </main>
    </div>

    @include('partials.site-footer')
    @include('partials.terms-privacy-modal')

    <script>
        document.querySelectorAll("[data-toggle-password]").forEach((button) => {
            button.addEventListener("click", () => {
                const input = document.getElementById(button.dataset.togglePassword);
                const icon = button.querySelector("i");
                const isHidden = input.type === "password";

                input.type = isHidden ? "text" : "password";
                button.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
                button.setAttribute("aria-pressed", isHidden ? "true" : "false");
                icon.classList.toggle("fa-eye", !isHidden);
                icon.classList.toggle("fa-eye-slash", isHidden);
            });
        });

        const legalModal = document.getElementById("legalModal");
        const showLegalTab = (tabName) => {
            legalModal.querySelectorAll("[data-legal-tab]").forEach((tab) => {
                tab.classList.toggle("is-active", tab.dataset.legalTab === tabName);
            });
            legalModal.querySelectorAll("[data-legal-panel]").forEach((panel) => {
                panel.classList.toggle("is-active", panel.dataset.legalPanel === tabName);
            });
        };

        document.querySelectorAll("[data-legal-open]").forEach((button) => {
            button.addEventListener("click", () => {
                showLegalTab(button.dataset.legalOpen);
                legalModal.classList.add("is-open");
                legalModal.setAttribute("aria-hidden", "false");
            });
        });

        legalModal.querySelectorAll("[data-legal-tab]").forEach((tab) => {
            tab.addEventListener("click", () => showLegalTab(tab.dataset.legalTab));
        });

        const closeLegalModal = () => {
            legalModal.classList.remove("is-open");
            legalModal.setAttribute("aria-hidden", "true");
        };

        legalModal.querySelector("[data-legal-close]").addEventListener("click", closeLegalModal);
        legalModal.addEventListener("click", (event) => {
            if (event.target === legalModal) closeLegalModal();
        });
        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && legalModal.classList.contains("is-open")) closeLegalModal();
        });
    </script>
</body>
</html>
