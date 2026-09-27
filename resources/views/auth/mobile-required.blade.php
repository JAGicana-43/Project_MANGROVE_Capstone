<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile App Required - Project M.A.N.G.R.O.V.E</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/favicon-badge.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo/favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo/apple-touch-icon.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --color-canvas: #F7F8F7;
            --color-surface: #FFFFFF;
            --color-text: #1C2620;
            --color-text-muted: #66766D;
            --color-border: #E1E6E2;
            --color-accent: #2F8F68;
            --color-accent-soft: #9FD8B8;
            --color-accent-contrast: #082016;
            --color-danger: #B42318;
            --color-warning: #9A5B00;
            --color-warning-bg: #FBF3E4;
            --color-warning-border: #EBD9B4;
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

        main {
            flex: 1;
            width: 100%;
            display: grid;
            place-items: center;
            padding: 1.5rem 0;
        }

        .notice-shell {
            width: min(760px, 100%);
            background: var(--color-surface);
            border-radius: 8px;
            border: 1px solid var(--color-border);
            box-shadow: 0 28px 80px rgba(12, 38, 29, 0.16);
            overflow: hidden;
        }

        .notice-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.75rem 2rem;
            background: var(--color-warning-bg);
            border-bottom: 1px solid var(--color-warning-border);
        }

        .notice-icon {
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #fff;
            color: var(--color-warning);
            font-size: 1.15rem;
            border: 1px solid var(--color-warning-border);
        }

        .notice-header h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.4rem;
            line-height: 1.25;
            margin-bottom: 0.35rem;
        }

        .notice-header p {
            color: var(--color-text-muted);
            font-size: 0.95rem;
        }

        .notice-body {
            padding: 2rem;
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 2rem;
        }

        .reasons h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            margin-bottom: 0.85rem;
        }

        .reasons ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        .reasons li {
            display: flex;
            gap: 0.65rem;
            font-size: 0.9rem;
            color: var(--color-text-muted);
            line-height: 1.5;
        }

        .reasons li i {
            flex-shrink: 0;
            margin-top: 0.2rem;
            color: var(--color-accent);
        }

        .reasons li strong {
            color: var(--color-text);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            padding: 0.65rem 1.1rem;
            border-radius: 8px;
            border: 1px solid var(--color-border);
            color: var(--color-text);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
        }
        .back-link:hover { border-color: var(--color-accent); color: var(--color-accent); }

        .qr-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 1.25rem;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            background: var(--color-canvas);
        }

        .qr-mock {
            width: 148px;
            height: 148px;
            border-radius: 6px;
            background:
                repeating-conic-gradient(var(--color-accent-contrast) 0% 25%, transparent 0% 50%)
                0 0 / 20% 20%;
            padding: 10px;
            background-color: #fff;
            border: 1px solid var(--color-border);
            position: relative;
        }
        .qr-mock::before,
        .qr-mock::after {
            content: "";
            position: absolute;
            width: 30px;
            height: 30px;
            border: 6px solid var(--color-accent-contrast);
            border-radius: 4px;
        }
        .qr-mock::before { top: 10px; left: 10px; }
        .qr-mock::after { bottom: 10px; left: 10px; }

        .qr-badge {
            margin-top: 0.9rem;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--color-warning);
            background: var(--color-warning-bg);
            border: 1px solid var(--color-warning-border);
            padding: 0.3rem 0.6rem;
            border-radius: 999px;
        }

        .qr-caption {
            margin-top: 0.75rem;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .qr-caption-sub {
            margin-top: 0.15rem;
            font-size: 0.78rem;
            color: var(--color-text-muted);
        }

        .site-footer {
            color: var(--color-text-muted);
            background: var(--color-surface);
            border-top: 1px solid var(--color-border);
            font-size: 0.82rem;
        }
        .site-footer-inner {
            width: min(1180px, 100%);
            margin: 0 auto;
            padding: 1.25rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .footer-brand { display: flex; align-items: center; gap: 0.7rem; }
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
        .footer-brand strong { display: block; color: var(--color-text); }
        .footer-meta { display: flex; gap: 0.9rem; flex-wrap: wrap; justify-content: flex-end; }
        .footer-meta a { color: var(--color-text-muted); text-decoration: none; }
        .footer-meta a:hover { color: var(--color-accent); }

        @media (max-width: 640px) {
            .notice-body { grid-template-columns: 1fr; }
            .notice-header { padding: 1.5rem; }
            .notice-body { padding: 1.5rem; }
            .site-footer-inner { padding: 1.25rem 1rem; }
            .footer-meta { justify-content: flex-start; }
        }
    </style>
</head>
<body>
    <main>
        <div class="notice-shell">
            <div class="notice-header">
                <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <div>
                    <h1>Field Researcher accounts are mobile-only</h1>
                    <p>This account type can't sign in through the web system. Use the Project M.A.N.G.R.O.V.E mobile app instead.</p>
                </div>
            </div>

            <div class="notice-body">
                <div class="reasons">
                    <h2>Why am I seeing this?</h2>
                    <ul>
                        <li>
                            <i class="fa-solid fa-location-dot"></i>
                            <span><strong>GPS-tagged sampling</strong> — soil readings and sampling points need to be captured with your device's live location, which only the mobile app records.</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-wifi"></i>
                            <span><strong>Offline field work</strong> — the mobile app lets you collect data in low-signal mangrove sites and syncs it once you're back online. The web dashboard has no offline mode.</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-shield-halved"></i>
                            <span><strong>Data integrity</strong> — keeping field data entry on one dedicated app prevents duplicate or conflicting entries between web and mobile.</span>
                        </li>
                    </ul>

                    <a href="{{ route('login') }}" class="back-link">
                        <i class="fa-solid fa-arrow-left"></i> Back to Login
                    </a>
                </div>

                <div class="qr-card">
                    <div class="qr-mock" role="img" aria-label="Placeholder QR code for the Project M.A.N.G.R.O.V.E mobile app"></div>
                    <span class="qr-badge">Mockup</span>
                    <div class="qr-caption">Install the Field App</div>
                    <div class="qr-caption-sub">App store links coming soon</div>
                </div>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <div class="site-footer-inner">
            <div class="footer-brand">
                <span class="footer-mark"><img src="{{ asset('images/logo/ProjMangrove_Logo.svg') }}" alt="Project M.A.N.G.R.O.V.E logo"></span>
                <div>
                    <strong>Project M.A.N.G.R.O.V.E</strong>
                </div>
            </div>
            <div class="footer-meta">
                <span>SPAMAST Malita Campus</span>
                <span>&copy; {{ date('Y') }} Project M.A.N.G.R.O.V.E</span>
            </div>
        </div>
    </footer>
</body>
</html>
