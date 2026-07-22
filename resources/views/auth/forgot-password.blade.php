<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Project MANGROVE</title>
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
            --deep: #0b261d;
            --green: #2f8f68;
            --mint: #9fd8b8;
            --paper: #f6f4ee;
            --white: #ffffff;
            --line: #dfe6df;
            --danger: #b42318;
        }

        body {
            min-height: 100vh;
            font-family: "Inter", sans-serif;
            color: var(--ink);
            background: var(--paper);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .auth-card {
            width: min(440px, 100%);
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 28px 80px rgba(12, 38, 29, 0.16);
            border: 1px solid rgba(11, 38, 29, 0.08);
            padding: clamp(1.75rem, 5vw, 2.75rem);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            font-weight: 800;
            margin-bottom: 1.75rem;
        }

        .brand-mark {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: rgba(47, 143, 104, 0.1);
            padding: 4px;
            box-sizing: border-box;
            flex-shrink: 0;
        }
        .brand-mark img { width: 100%; height: 100%; object-fit: contain; display: block; }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: var(--green);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        h2 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(1.5rem, 4vw, 1.9rem);
            line-height: 1.15;
            margin-bottom: 0.55rem;
        }

        .subtitle {
            color: var(--muted);
            margin-bottom: 1.6rem;
            font-size: 0.92rem;
            line-height: 1.5;
        }

        .alert {
            border-radius: 8px;
            padding: 0.85rem 0.95rem;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
        }

        .alert.error {
            color: var(--danger);
            background: #fff1ef;
            border-color: #ffd2cc;
        }

        .alert.success {
            color: #216346;
            background: #eef7f1;
            border-color: #cfe8d7;
        }

        label {
            display: block;
            font-size: 0.84rem;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 0.45rem;
        }

        .field {
            position: relative;
            margin-bottom: 1.1rem;
        }

        .field i {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #8b9a90;
        }

        input[type="email"] {
            width: 100%;
            min-height: 48px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 0.78rem 0.9rem 0.78rem 2.55rem;
            color: var(--ink);
            background: #fbfcfa;
            font: inherit;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--green);
            background: var(--white);
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
            color: #082016;
            background: var(--mint);
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            transition: transform 0.2s ease, background 0.2s ease;
        }

        button:hover {
            transform: translateY(-2px);
            background: #8fd0aa;
        }

        .back-to-login {
            margin-top: 1.25rem;
            text-align: center;
            font-size: 0.9rem;
            color: var(--muted);
        }

        .back-to-login a {
            color: var(--green);
            font-weight: 800;
            text-decoration: none;
        }

        .back-to-login a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="brand">
            <span class="brand-mark"><img src="{{ asset('images/logo/ProjMangrove_Logo.svg') }}" alt="Project MANGROVE logo"></span>
            <span>Project MANGROVE</span>
        </div>

        <a href="{{ route('login') }}" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to log in</a>

        <h2>Forgot your password?</h2>
        <p class="subtitle">Enter the email address on your account and we'll send you a link to reset your password.</p>

        @if (session('status'))
            <div class="alert success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" data-loading-label="Sending reset link...">
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

            <button type="submit"><i class="fa-solid fa-paper-plane"></i> Send reset link</button>
        </form>

        <p class="back-to-login">Remembered your password? <a href="{{ route('login') }}">Log in</a></p>
    </div>
</body>
</html>
