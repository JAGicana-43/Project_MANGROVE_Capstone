<style>
    /* Shared motion layer for the lobby, workspace and admin layouts.
       Entrance animations use fill-mode "backwards" on purpose: "both" or
       "forwards" would leave a transform on the element after the
       animation ends, which overrides hover transforms and turns the
       element into a containing block for fixed-position children (the
       lobby's modals live inside the page content). */
    :root {
        --motion-ease: cubic-bezier(0.22, 1, 0.36, 1);
    }

    html {
        scroll-behavior: smooth;
    }

    @keyframes motionFadeIn {
        from { opacity: 0; }
        to   { opacity: 1; }
    }

    @keyframes motionFadeUp {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    @keyframes motionMenuIn {
        from { opacity: 0; transform: translateY(-6px) scale(0.97); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    @keyframes motionModalIn {
        from { opacity: 0; transform: translate(-50%, -47%) scale(0.97); }
        to   { opacity: 1; transform: translate(-50%, -50%) scale(1); }
    }

    @keyframes motionPinDrop {
        0%   { opacity: 0; transform: translateY(-16px); }
        60%  { opacity: 1; transform: translateY(2px); }
        100% { opacity: 1; transform: translateY(0); }
    }

    /* ---- page + block entrances ---- */
    .page-content:not(.page-content--full) {
        animation: motionFadeUp 0.4s var(--motion-ease) backwards;
    }

    .atlas-summary,
    .atlas-legend {
        animation: motionFadeUp 0.45s var(--motion-ease) 0.08s backwards;
    }

    .atlas-strip {
        animation: motionFadeUp 0.45s var(--motion-ease) 0.16s backwards;
    }

    .stat-tile,
    .table-card,
    .dock-section {
        animation: motionFadeUp 0.4s var(--motion-ease) backwards;
    }

    /* Staggered cards: at most 8 projects per researcher, so a short
       fixed ladder covers every case. */
    .atlas-pcard, .pidx-card, .stat-tile, .dock-section { animation-delay: 0s; }
    .atlas-pcard:nth-child(2), .pidx-card:nth-child(2), .stat-tile:nth-child(2), .dock-section:nth-child(2) { animation-delay: 0.05s; }
    .atlas-pcard:nth-child(3), .pidx-card:nth-child(3), .stat-tile:nth-child(3), .dock-section:nth-child(3) { animation-delay: 0.10s; }
    .atlas-pcard:nth-child(4), .pidx-card:nth-child(4), .stat-tile:nth-child(4), .dock-section:nth-child(4) { animation-delay: 0.15s; }
    .atlas-pcard:nth-child(5), .pidx-card:nth-child(5), .stat-tile:nth-child(5), .dock-section:nth-child(5) { animation-delay: 0.20s; }
    .atlas-pcard:nth-child(6), .pidx-card:nth-child(6), .stat-tile:nth-child(6), .dock-section:nth-child(6) { animation-delay: 0.25s; }
    .atlas-pcard:nth-child(n+7), .pidx-card:nth-child(n+7), .stat-tile:nth-child(n+7), .dock-section:nth-child(n+7) { animation-delay: 0.30s; }

    .atlas-pcard, .pidx-card {
        animation: motionFadeUp 0.4s var(--motion-ease) backwards;
    }

    /* ---- hover / press feedback ---- */
    .atlas-pcard,
    .pidx-card,
    .atlas-todo a,
    .stat-tile {
        transition: transform 0.2s var(--motion-ease), box-shadow 0.2s var(--motion-ease), border-color 0.2s ease;
    }

    .atlas-pcard:hover,
    .pidx-card:hover,
    .atlas-todo a:hover,
    .stat-tile:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 26px hsl(var(--shadow-color) / 0.16);
    }

    .btn-primary,
    .btn-secondary,
    .atlas-btn-solid,
    .atlas-viewall,
    .aoi-card a.aoi-card-btn,
    .pidx-btn-solid,
    .icon-btn,
    .user-menu-item,
    .atlas-filters button,
    .pidx-filters button,
    .atlas-tabs button {
        transition: transform 0.14s ease, background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .btn-primary:not(:disabled):active,
    .btn-secondary:not(:disabled):active,
    .atlas-btn-solid:active,
    .atlas-viewall:active,
    .aoi-card a.aoi-card-btn:active,
    .pidx-btn-solid:active,
    .icon-btn:active {
        transform: scale(0.96);
    }

    /* ---- menus, modals, drawers ---- */
    .user-menu-dropdown:not([hidden]) {
        transform-origin: top right;
        animation: motionMenuIn 0.18s var(--motion-ease);
    }

    .modal-backdrop.is-open,
    .drawer-backdrop {
        animation: motionFadeIn 0.2s ease;
    }

    .modal-card.is-open {
        animation: motionModalIn 0.26s var(--motion-ease);
    }

    .drawer,
    .create-modal {
        animation: motionFadeIn 0.22s ease;
    }

    /* ---- map pins ---- */
    .lobby-pin {
        animation: motionPinDrop 0.5s var(--motion-ease) 0.3s backwards;
    }

    @media (prefers-reduced-motion: reduce) {
        html {
            scroll-behavior: auto;
        }

        *,
        *::before,
        *::after {
            animation-duration: 0.01ms !important;
            animation-delay: 0s !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }
</style>
