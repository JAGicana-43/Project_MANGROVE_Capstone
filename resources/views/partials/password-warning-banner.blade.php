@if (Auth::check() && Auth::user()->must_change_password)
    <div class="password-warning-banner">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>Your password was set by an administrator. For your account's security, please change it now.</span>
        <a href="{{ route('settings.account') }}#securityCard">Change password</a>
    </div>

    <style>
        .password-warning-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 18px;
            background: rgba(240, 165, 0, 0.14);
            color: #8A5B00;
            border-bottom: 1px solid rgba(240, 165, 0, 0.32);
            font-size: 13.5px;
            font-weight: 600;
        }

        [data-theme="dark"] .password-warning-banner {
            background: rgba(240, 165, 0, 0.16);
            color: #F0C266;
        }

        .password-warning-banner i {
            color: #F0A500;
            flex: 0 0 auto;
        }

        .password-warning-banner span {
            flex: 1;
        }

        .password-warning-banner a {
            flex: 0 0 auto;
            color: inherit;
            text-decoration: underline;
            font-weight: 700;
            white-space: nowrap;
        }
    </style>
@endif
