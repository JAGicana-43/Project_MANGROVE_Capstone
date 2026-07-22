<style>
    .page-transition-progress {
        position: fixed;
        inset: 0 0 auto 0;
        z-index: 5000;
        height: 3px;
        pointer-events: none;
        opacity: 0;
        background: rgba(116, 198, 157, 0.18);
        transition: opacity 0.16s ease;
    }

    .page-transition-progress span {
        display: block;
        width: 0%;
        height: 100%;
        border-radius: 0 999px 999px 0;
        background: linear-gradient(90deg, var(--sage-green, #74C69D), var(--forest-green, #2D6A4F), var(--amber, #F0A500));
        box-shadow: 0 0 18px rgba(116, 198, 157, 0.72);
        transition: width 0.45s cubic-bezier(0.22, 1, 0.36, 1);
    }

    .page-transition-overlay {
        position: fixed;
        inset: 0;
        z-index: 4990;
        display: grid;
        place-items: center;
        padding: 24px;
        pointer-events: none;
        opacity: 0;
        visibility: hidden;
        background: rgba(248, 249, 250, 0.64);
        backdrop-filter: blur(5px);
        transition: opacity 0.2s ease, visibility 0.2s ease;
    }

    .page-transition-card {
        min-width: min(320px, calc(100vw - 48px));
        max-width: 380px;
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px 18px;
        border: 1px solid rgba(255, 255, 255, 0.72);
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.92);
        color: var(--dark-gray, #212529);
        box-shadow: 0 18px 42px rgba(33, 37, 41, 0.18);
        transform: translateY(8px) scale(0.98);
        transition: transform 0.2s ease;
    }

    .page-transition-spinner {
        width: 34px;
        height: 34px;
        flex: 0 0 auto;
        border-radius: 50%;
        border: 3px solid rgba(45, 106, 79, 0.14);
        border-top-color: var(--forest-green, #2D6A4F);
        animation: pageTransitionSpin 0.72s linear infinite;
    }

    .page-transition-copy {
        min-width: 0;
    }

    .page-transition-title {
        display: block;
        color: var(--forest-green, #2D6A4F);
        font-weight: 800;
        font-size: 13px;
        line-height: 1.2;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .page-transition-message {
        display: block;
        margin-top: 3px;
        color: rgba(33, 37, 41, 0.68);
        font-size: 13px;
        font-weight: 650;
        line-height: 1.35;
    }

    body.page-transition-ready .page-content,
    body.page-transition-ready .map-shell,
    body.page-transition-ready .legacy-content {
        animation: pageContentEnter 0.28s ease both;
    }

    body.is-navigating .page-transition-progress {
        opacity: 1;
    }

    body.is-navigating .page-transition-overlay {
        opacity: 1;
        visibility: visible;
    }

    body.is-navigating .page-transition-card {
        transform: translateY(0) scale(1);
    }

    body.is-navigating .page-content,
    body.is-navigating .map-shell,
    body.is-navigating .legacy-content {
        opacity: 0.84;
        filter: saturate(0.92);
    }

    a.is-transitioning,
    button.is-submitting {
        position: relative;
    }

    a.is-transitioning {
        color: var(--forest-green, #2D6A4F);
    }

    button.is-submitting {
        cursor: wait;
        opacity: 0.76;
    }

    @keyframes pageTransitionSpin {
        to { transform: rotate(360deg); }
    }

    @keyframes pageContentEnter {
        from {
            opacity: 0;
            transform: translateY(8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .page-transition-progress span,
        .page-transition-overlay,
        .page-transition-card,
        body.page-transition-ready .page-content,
        body.page-transition-ready .map-shell,
        body.page-transition-ready .legacy-content {
            animation: none;
            transition: none;
        }
    }
</style>
