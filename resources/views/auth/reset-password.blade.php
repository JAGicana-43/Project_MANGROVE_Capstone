<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Project MANGROVE</title>
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
            --forest-green: #2D6A4F;
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

        label {
            display: block;
            font-size: 0.84rem;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 0.45rem;
        }

        .field {
            position: relative;
            margin-bottom: 0.4rem;
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
        input[type="text"].password-input {
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

        input[readonly] {
            background: rgba(20, 35, 28, 0.04);
            color: var(--muted);
        }

        .field.password-field input {
            padding-right: 3rem;
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
            color: #617268;
            background: transparent;
            box-shadow: none;
        }

        .peek-password:hover {
            transform: translateY(-50%);
            background: #eef5ef;
        }

        input:focus {
            outline: none;
            border-color: var(--green);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(47, 143, 104, 0.14);
        }

        .password-strength {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 0.85rem;
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
            background: var(--danger);
            transition: width 0.2s ease, background 0.2s ease;
        }

        .password-strength-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            white-space: nowrap;
        }

        .password-requirements {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 1.1rem;
            font-size: 12px;
            color: var(--muted);
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

        <h2>Reset your password</h2>
        <p class="subtitle">Choose a new password for your account.</p>

        @if ($errors->any())
            <div class="alert error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" data-loading-label="Resetting password...">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <label for="email">Email address</label>
            <div class="field">
                <i class="fa-solid fa-envelope"></i>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email', $email) }}"
                    autocomplete="email"
                    required
                >
            </div>

            <label for="password">New password</label>
            <div class="field password-field">
                <i class="fa-solid fa-key"></i>
                <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" required>
                <button type="button" class="peek-password" data-toggle-password="password" aria-label="Show password">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
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

            <label for="password_confirmation">Confirm new password</label>
            <div class="field password-field">
                <i class="fa-solid fa-key"></i>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="8" required>
                <button type="button" class="peek-password" data-toggle-password="password_confirmation" aria-label="Show password">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>

            <button type="submit"><i class="fa-solid fa-key"></i> Reset password</button>
        </form>

        <p class="back-to-login">Remembered it after all? <a href="{{ route('login') }}">Log in</a></p>
    </div>

    <script>
        document.querySelectorAll("[data-toggle-password]").forEach((button) => {
            button.addEventListener("click", () => {
                const input = document.getElementById(button.dataset.togglePassword);
                const icon = button.querySelector("i");
                const isHidden = input.type === "password";

                input.type = isHidden ? "text" : "password";
                button.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
                icon.classList.toggle("fa-eye", !isHidden);
                icon.classList.toggle("fa-eye-slash", isHidden);
            });
        });

        (function () {
            var input = document.getElementById('password');
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
