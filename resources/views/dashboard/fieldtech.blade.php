<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Field Technician Dashboard - Project MANGROVE</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/favicon-badge.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo/favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo/apple-touch-icon.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --ink: #14231c;
            --muted: #647268;
            --green: #2f8f68;
            --paper: #f6f4ee;
            --white: #ffffff;
            --line: #dfe6df;
        }
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: "Inter", sans-serif;
            color: var(--ink);
            background: var(--paper);
        }
        main {
            flex: 1;
            display: grid;
            place-items: center;
            padding: 2rem;
        }
        .card {
            width: min(640px, 100%);
            background: var(--white);
            padding: 2rem;
            border-radius: 8px;
            border: 1px solid var(--line);
            box-shadow: 0 20px 60px rgba(12, 38, 29, 0.12);
        }
        h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.55rem;
            margin-bottom: 0.5rem;
        }
        p { color: var(--muted); margin-bottom: 1rem; }
        .card-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        button, .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 1rem;
            background: #b42318;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            text-decoration: none;
        }
        .btn-secondary {
            background: transparent;
            color: var(--ink);
            border: 1px solid var(--line);
        }
        .site-footer {
            color: var(--muted);
            background: var(--white);
            border-top: 1px solid var(--line);
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
        .footer-brand strong { display: block; color: var(--ink); }
        .footer-brand span { display: block; }
        .footer-meta { display: flex; gap: 0.9rem; flex-wrap: wrap; justify-content: flex-end; }
        .footer-meta a { color: var(--muted); text-decoration: none; }
        .footer-meta a:hover { color: var(--green); }
        @media (max-width: 640px) {
            main { padding: 1rem; place-items: stretch; }
            .site-footer-inner { padding: 1.25rem 1rem; }
            .footer-meta { justify-content: flex-start; }
        }
    </style>
    @include('partials.page-transition-styles')
</head>
<body>
    @include('partials.page-transition')
    @include('partials.logout-modal')
    @include('partials.password-warning-banner')
    <main>
        <div class="card">
            <h1>Welcome, {{ $user->name }}</h1>
            <p>Role: <strong>Field Technician</strong></p>
            <p>You are logged in as a field technician. Your assigned sampling points will appear here.</p>

            <div class="card-actions">
                <a href="{{ route('settings.account') }}" class="btn-secondary">
                    <i class="fa-solid fa-gear"></i> Account Settings
                </a>
                <form method="POST" action="{{ route('logout') }}" data-loading-label="Logging out...">
                    @csrf
                    <button type="submit" onclick="return openLogoutModal(event)"><i class="fa-solid fa-arrow-right-from-bracket"></i> Log Out</button>
                </form>
            </div>
        </div>
    </main>

    @include('partials.site-footer')
</body>
</html>
