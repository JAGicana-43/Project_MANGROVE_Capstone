<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project MANGROVE - Environmental Monitoring and Spatial Decision Support</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/favicon-badge.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo/favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo/apple-touch-icon.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --ink: #15231c;
            --muted: #63736a;
            --deep: #0b261d;
            --forest: #1f5f46;
            --green: #2f8f68;
            --mint: #9fd8b8;
            --water: #1f7a8c;
            --clay: #c8874a;
            --paper: #f6f4ee;
            --white: #ffffff;
            --line: #dfe6df;
            --shadow: 0 24px 70px rgba(16, 38, 29, 0.14);
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: "Inter", sans-serif;
            color: var(--ink);
            background: var(--paper);
            line-height: 1.6;
            overflow-x: hidden;
        }

        img { display: block; max-width: 100%; }
        a { color: inherit; }

        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 5vw;
            color: var(--white);
            transition: background 0.25s ease, box-shadow 0.25s ease;
        }

        .navbar.scrolled {
            background: rgba(11, 38, 29, 0.94);
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            font-weight: 800;
            letter-spacing: 0.02em;
            font-size: 1.4rem;
        }

        .brand-mark {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #fff;
            padding: 5px;
            box-sizing: border-box;
            flex-shrink: 0;
        }
        .brand-mark img { width: 100%; height: 100%; object-fit: contain; display: block; }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.35rem;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.78);
        }

        .nav-links a {
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .nav-links a:hover { color: var(--white); }

        .nav-login {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.62rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 8px;
            color: var(--white);
            text-decoration: none;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.1);
        }

        .hero {
            min-height: 92vh;
            position: relative;
            display: grid;
            align-items: end;
            padding: 8rem 5vw 4rem;
            color: var(--white);
            overflow: hidden;
            background: var(--deep);
        }

        .hero-bg-layer {
            position: absolute;
            inset: 0;
            z-index: 0;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1.8s ease;
            animation: heroKenBurns 14s ease-in-out infinite alternate;
        }

        .hero-bg.is-active { opacity: 1; }

        @keyframes heroKenBurns {
            from { transform: scale(1); }
            to { transform: scale(1.08); }
        }

        .hero-gradient {
            position: absolute;
            inset: 0;
            z-index: 1;
            background: linear-gradient(90deg, rgba(8, 28, 21, 0.88) 0%, rgba(8, 28, 21, 0.68) 43%, rgba(8, 28, 21, 0.22) 100%);
        }

        .hero::after {
            content: "";
            position: absolute;
            inset: auto 0 0;
            height: 160px;
            z-index: 1;
            background: linear-gradient(180deg, rgba(246, 244, 238, 0), var(--paper));
            pointer-events: none;
        }

        @keyframes fadeUpIn {
            from { opacity: 0; transform: translateY(26px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hero-inner {
            position: relative;
            z-index: 2;
            width: min(1180px, 100%);
            margin: 0 auto;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            width: fit-content;
            padding: 0.4rem 0.7rem;
            border-radius: 999px;
            background: rgba(159, 216, 184, 0.14);
            border: 1px solid rgba(159, 216, 184, 0.34);
            color: #c8f0d8;
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 1.3rem;
            opacity: 0;
            animation: fadeUpIn 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.1s forwards;
        }

        .hero h1 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(3rem, 8vw, 6.8rem);
            line-height: 0.92;
            letter-spacing: 0;
            max-width: 820px;
            margin-bottom: 1.25rem;
            opacity: 0;
            animation: fadeUpIn 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.24s forwards;
        }

        .hero h1 span { color: var(--mint); }

        .hero-copy {
            max-width: 650px;
            color: rgba(255, 255, 255, 0.78);
            font-size: clamp(1rem, 2vw, 1.15rem);
            margin-bottom: 2rem;
            opacity: 0;
            animation: fadeUpIn 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.38s forwards;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.85rem;
            align-items: center;
            opacity: 0;
            animation: fadeUpIn 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.5s forwards;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            min-height: 46px;
            padding: 0.78rem 1.2rem;
            border-radius: 8px;
            font-weight: 800;
            text-decoration: none;
            border: 1px solid transparent;
            transition: transform 0.2s ease, background 0.2s ease, border-color 0.2s ease;
        }

        .button:hover { transform: translateY(-2px); }
        .button-primary { background: var(--mint); color: var(--deep); }
        .button-ghost { color: var(--white); border-color: rgba(255, 255, 255, 0.3); background: rgba(255, 255, 255, 0.08); }

        section { padding: 5.5rem 5vw; }

        .section-inner {
            width: min(1180px, 100%);
            margin: 0 auto;
        }

        .section-grid {
            display: grid;
            grid-template-columns: 0.9fr 1.1fr;
            gap: 3.5rem;
            align-items: start;
        }

        .section-label {
            color: var(--green);
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-size: 0.75rem;
            font-weight: 800;
            margin-bottom: 0.7rem;
        }

        .section-title {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(2rem, 4vw, 3.1rem);
            line-height: 1.05;
            margin-bottom: 1rem;
        }

        .section-copy {
            color: var(--muted);
            max-width: 640px;
            font-size: 1rem;
        }

        .capability-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 3rem;
        }

        .capability {
            min-height: 270px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-radius: 8px;
            background: var(--white);
            border: 1px solid var(--line);
            padding: 1.35rem;
            box-shadow: 0 16px 42px rgba(34, 53, 43, 0.07);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s ease, border-color 0.3s ease;
        }

        .capability:hover {
            transform: translateY(-6px);
            box-shadow: 0 26px 54px rgba(34, 53, 43, 0.14);
            border-color: rgba(47, 143, 104, 0.35);
        }

        .capability i {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: #e8f3ed;
            color: var(--forest);
            margin-bottom: 1.2rem;
            transition: transform 0.3s ease, background 0.3s ease, color 0.3s ease;
        }

        .capability:hover i {
            background: var(--forest);
            color: var(--white);
            transform: scale(1.08);
        }

        .capability small {
            color: var(--water);
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .capability h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.25rem;
            margin: 0.35rem 0 0.55rem;
        }

        .capability p {
            color: var(--muted);
            font-size: 0.92rem;
        }

        .photo-section {
            background: var(--white);
        }

        .photo-layout {
            display: grid;
            grid-template-columns: 1fr 0.82fr;
            gap: 1.25rem;
            align-items: stretch;
            margin-top: 3rem;
        }

        .photo-large,
        .photo-stack figure {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            min-height: 340px;
            background: #d8e3db;
        }

        .photo-large img,
        .photo-stack img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .photo-large:hover img,
        .photo-stack figure:hover img {
            transform: scale(1.06);
        }

        .photo-stack {
            display: grid;
            grid-template-rows: 1fr 1fr;
            gap: 1.25rem;
        }

        .photo-stack figure { min-height: 230px; }

        figcaption {
            position: absolute;
            left: 1rem;
            right: 1rem;
            bottom: 1rem;
            padding: 0.75rem;
            border-radius: 8px;
            background: rgba(8, 28, 21, 0.78);
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.84rem;
            backdrop-filter: blur(10px);
        }

        .workflow {
            background: #eef3ed;
        }

        .timeline {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0.75rem;
            margin-top: 3rem;
        }

        .step {
            border-radius: 8px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.82);
            padding: 1rem;
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s ease, border-color 0.3s ease;
        }

        .step:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(34, 53, 43, 0.1);
            border-color: rgba(47, 143, 104, 0.35);
        }

        .step-num {
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: var(--green);
            font-weight: 800;
            margin-bottom: 1.5rem;
        }

        .step h3 {
            font-size: 0.98rem;
            line-height: 1.25;
            margin-bottom: 0.45rem;
        }

        .step p {
            color: var(--muted);
            font-size: 0.84rem;
        }

        .impact {
            color: var(--white);
            background:
                linear-gradient(90deg, rgba(11, 38, 29, 0.92), rgba(11, 38, 29, 0.76)),
                url("{{ asset('images/landing/mangrove-plantation-philippines.jpg') }}") center / cover;
        }

        .impact .section-label { color: var(--mint); }
        .impact .section-copy { color: rgba(255, 255, 255, 0.7); }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 3rem;
        }

        .metric {
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.08);
            padding: 1.2rem;
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), background 0.3s ease, border-color 0.3s ease;
        }

        .metric:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(159, 216, 184, 0.4);
        }

        .metric strong {
            display: block;
            font-family: 'Poppins', sans-serif;
            font-size: 1.55rem;
            margin-bottom: 0.35rem;
        }

        .metric span {
            color: rgba(255, 255, 255, 0.68);
            font-size: 0.84rem;
        }

        .cta-band {
            padding: 3.5rem 5vw;
            background: var(--paper);
        }

        .cta-inner {
            width: min(1180px, 100%);
            margin: 0 auto;
            border-radius: 8px;
            background: var(--deep);
            color: var(--white);
            padding: clamp(1.5rem, 4vw, 2.5rem);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
        }

        .cta-inner h2 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(1.5rem, 3vw, 2.2rem);
            line-height: 1.1;
            margin-bottom: 0.4rem;
        }

        .cta-inner p { color: rgba(255, 255, 255, 0.68); max-width: 620px; }

        .site-footer {
            background: #081c15;
            color: rgba(255, 255, 255, 0.62);
            padding: 4rem 5vw 2rem;
        }

        .footer-top {
            width: min(1180px, 100%);
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.6fr repeat(3, 1fr);
            gap: 2.5rem;
        }

        .footer-col h4 {
            font-family: 'Poppins', sans-serif;
            color: var(--white);
            font-size: 0.92rem;
            letter-spacing: 0.02em;
            margin-bottom: 1.1rem;
        }

        .footer-col {
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
            font-size: 0.88rem;
        }

        .footer-col a {
            color: rgba(255, 255, 255, 0.62);
            text-decoration: none;
            width: fit-content;
            transition: color 0.15s ease;
        }

        .footer-col a:hover { color: var(--mint); }

        .footer-col p { margin: 0; line-height: 1.5; }

        .footer-brand-col { gap: 1rem; }

        .footer-brand {
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            text-decoration: none;
        }

        .footer-brand-mark {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #fff;
            display: grid;
            place-items: center;
            padding: 4px;
            box-sizing: border-box;
            flex-shrink: 0;
        }

        .footer-brand-mark img { width: 100%; height: 100%; object-fit: contain; display: block; }

        .footer-brand-name {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            color: var(--white);
            font-size: 1.05rem;
        }

        .footer-tagline {
            max-width: 300px;
            line-height: 1.6;
            font-size: 0.88rem;
        }

        .footer-socials {
            display: flex;
            gap: 0.6rem;
            margin-top: 0.3rem;
        }

        .footer-socials a {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, 0.18);
            color: rgba(255, 255, 255, 0.75);
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }

        .footer-socials a:hover {
            background: var(--mint);
            border-color: var(--mint);
            color: var(--deep);
        }

        .footer-divider {
            width: min(1180px, 100%);
            margin: 2.75rem auto 1.5rem;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .footer-bottom {
            width: min(1180px, 100%);
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            font-size: 0.8rem;
        }

        .footer-legal { display: flex; gap: 1.25rem; }
        .footer-legal a { color: rgba(255, 255, 255, 0.62); text-decoration: none; }
        .footer-legal a:hover { color: var(--mint); }

        .credits a { color: var(--mint); text-decoration: none; }

        .reveal {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1), transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .reveal.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        .capability-grid.reveal.is-visible .capability:nth-child(1) { transition-delay: 0s; }
        .capability-grid.reveal.is-visible .capability:nth-child(2) { transition-delay: 0.12s; }
        .capability-grid.reveal.is-visible .capability:nth-child(3) { transition-delay: 0.24s; }

        .timeline.reveal.is-visible .step:nth-child(1) { transition-delay: 0s; }
        .timeline.reveal.is-visible .step:nth-child(2) { transition-delay: 0.1s; }
        .timeline.reveal.is-visible .step:nth-child(3) { transition-delay: 0.2s; }
        .timeline.reveal.is-visible .step:nth-child(4) { transition-delay: 0.3s; }
        .timeline.reveal.is-visible .step:nth-child(5) { transition-delay: 0.4s; }

        .metric-grid.reveal.is-visible .metric:nth-child(1) { transition-delay: 0s; }
        .metric-grid.reveal.is-visible .metric:nth-child(2) { transition-delay: 0.1s; }
        .metric-grid.reveal.is-visible .metric:nth-child(3) { transition-delay: 0.2s; }
        .metric-grid.reveal.is-visible .metric:nth-child(4) { transition-delay: 0.3s; }

        .capability-grid.reveal .capability,
        .timeline.reveal .step,
        .metric-grid.reveal .metric {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1), transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .capability-grid.reveal.is-visible .capability,
        .timeline.reveal.is-visible .step,
        .metric-grid.reveal.is-visible .metric {
            opacity: 1;
            transform: translateY(0);
        }

        @media (prefers-reduced-motion: reduce) {
            .hero-bg { animation: none !important; }
            .eyebrow, .hero h1, .hero-copy, .hero-actions {
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
            }
            .reveal, .reveal .capability, .reveal .step, .reveal .metric {
                opacity: 1 !important;
                transform: none !important;
                transition: none !important;
            }
            .photo-large img, .photo-stack img, .capability, .step, .metric {
                transition: none !important;
            }
        }

        @media (max-width: 900px) {
            .footer-top { grid-template-columns: 1fr 1fr; }
            .footer-brand-col { grid-column: 1 / -1; }
        }

        @media (max-width: 560px) {
            .footer-top { grid-template-columns: 1fr; }
            .footer-bottom { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
        }

        @media (max-width: 980px) {
            .section-grid,
            .photo-layout {
                grid-template-columns: 1fr;
            }

            .capability-grid { grid-template-columns: 1fr; }
            .timeline, .metric-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 720px) {
            .nav-links a:not(.nav-login) { display: none; }
            .navbar { padding: 0.85rem 4vw; }
            .hero { min-height: auto; padding: 7.25rem 4vw 4rem; }
            section { padding: 4.25rem 4vw; }
            .hero h1 { font-size: clamp(2.7rem, 16vw, 4.2rem); }
            .timeline, .metric-grid { grid-template-columns: 1fr; }
            .photo-large, .photo-stack figure { min-height: 260px; }
            .cta-inner { align-items: stretch; flex-direction: column; }
            .button { width: 100%; }
        }
    </style>
    @include('partials.page-transition-styles')
</head>
<body>
    @include('partials.page-transition')
    <nav class="navbar" id="navbar">
        <a href="{{ route('landing') }}" class="brand">
            <span class="brand-mark"><img src="{{ asset('images/logo/ProjMangrove_Logo.svg') }}" alt="Project MANGROVE logo"></span>
            <span>Project MANGROVE</span>
        </a>

        <div class="nav-links">
            <a href="#capabilities">Capabilities</a>
            <a href="#fieldwork">Fieldwork</a>
            <a href="#workflow">Workflow</a>
            <a href="{{ route('login') }}" class="nav-login"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
        </div>
    </nav>

    <main>
        <section class="hero" id="home">
            <div class="hero-bg-layer" id="heroBgLayer">
                <div class="hero-bg is-active" style="background-image: url('{{ asset('images/landing/mangrove-forest-philippines.jpg') }}')"></div>
                <div class="hero-bg" style="background-image: url('{{ asset('images/landing/mangrove-plantation-philippines.jpg') }}')"></div>
            </div>
            <div class="hero-gradient"></div>
            <div class="hero-inner">
                <div>
                    <div class="eyebrow"><i class="fa-solid fa-satellite"></i> GIS, AI and field data platform</div>
                    <h1>Better evidence for <span>mangrove</span> rehabilitation.</h1>
                    <p class="hero-copy">
                        Project MANGROVE helps teams monitor coastal areas, classify rehabilitation suitability, and turn drone imagery, soil readings, and GIS layers into defensible field decisions.
                    </p>
                    <div class="hero-actions">
                        <a href="{{ route('login') }}" class="button button-primary"><i class="fa-solid fa-lock"></i> Access Platform</a>
                        <a href="#capabilities" class="button button-ghost"><i class="fa-solid fa-compass"></i> Explore System</a>
                    </div>
                </div>
            </div>
        </section>

        <section id="capabilities">
            <div class="section-inner">
                <div class="section-grid reveal">
                    <div>
                        <div class="section-label">Platform Capabilities</div>
                        <h2 class="section-title">A single workflow from coast mapping to action.</h2>
                    </div>
                    <p class="section-copy">
                        The system connects aerial survey outputs, AI classification, field sampling, and spatial overlays so researchers and field technicians can evaluate where rehabilitation is most likely to succeed.
                    </p>
                </div>

                <div class="capability-grid reveal">
                    <article class="capability">
                        <div>
                            <i class="fa-solid fa-map-location-dot"></i>
                            <small>Monitor</small>
                            <h3>Survey coastal sites</h3>
                            <p>Organize drone missions, areas of interest, orthomosaics, and environmental observations in one spatial record.</p>
                        </div>
                    </article>

                    <article class="capability">
                        <div>
                            <i class="fa-solid fa-brain"></i>
                            <small>Classify</small>
                            <h3>Detect suitability signals</h3>
                            <p>Use AI-assisted classification and field measurements to separate viable zones from low-priority or risky sites.</p>
                        </div>
                    </article>

                    <article class="capability">
                        <div>
                            <i class="fa-solid fa-file-lines"></i>
                            <small>Report</small>
                            <h3>Deliver decision-ready outputs</h3>
                            <p>Produce suitability maps, project summaries, and evidence trails that help teams justify rehabilitation choices.</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="photo-section" id="fieldwork">
            <div class="section-inner">
                <div class="section-grid reveal">
                    <div>
                        <div class="section-label">Real Field Context</div>
                        <h2 class="section-title">Built around actual mangrove landscapes, not placeholder graphics.</h2>
                    </div>
                    <p class="section-copy">
                        Rehabilitation planning depends on texture: tidal water, mudflat exposure, canopy cover, access paths, and human activity. The interface now uses real mangrove photography to make the system feel grounded and credible.
                    </p>
                </div>

                <div class="photo-layout reveal">
                    <figure class="photo-large">
                        <img src="{{ asset('images/landing/mangrove-forest-philippines.jpg') }}" alt="Mangrove forest at Las Pinas-Paranaque Critical Habitat and Ecotourism Area in Metro Manila, Philippines">
                        <figcaption>Mangrove forest context for coastal monitoring, sampling, and spatial assessment.</figcaption>
                    </figure>
                    <div class="photo-stack">
                        <figure>
                            <img src="{{ asset('images/landing/mangrove-plantation-philippines.jpg') }}" alt="Bakhawan Eco-Park mangrove plantation in the Philippines">
                            <figcaption>Restoration sites need repeatable records from survey to reporting.</figcaption>
                        </figure>
                        <figure>
                            <img src="{{ asset('images/landing/mangrove-forest-philippines.jpg') }}" alt="Dense mangrove canopy and tidal water">
                            <figcaption>GIS layers and soil readings help prioritize where teams should act first.</figcaption>
                        </figure>
                    </div>
                </div>
            </div>
        </section>

        <section class="workflow" id="workflow">
            <div class="section-inner">
                <div class="section-grid reveal">
                    <div>
                        <div class="section-label">Operational Workflow</div>
                        <h2 class="section-title">Five steps from UAV survey to rehabilitation recommendation.</h2>
                    </div>
                    <p class="section-copy">
                        Each stage leaves a clearer evidence trail for researchers, field technicians, and administrators managing rehabilitation projects.
                    </p>
                </div>

                <div class="timeline reveal">
                    <article class="step">
                        <div class="step-num">01 <i class="fa-solid fa-camera-retro"></i></div>
                        <h3>Capture imagery</h3>
                        <p>Register drone missions and upload orthomosaic outputs for target coastal areas.</p>
                    </article>
                    <article class="step">
                        <div class="step-num">02 <i class="fa-solid fa-layer-group"></i></div>
                        <h3>Map site layers</h3>
                        <p>Connect AOIs, classified tiles, and spatial overlays in a shared project view.</p>
                    </article>
                    <article class="step">
                        <div class="step-num">03 <i class="fa-solid fa-flask-vial"></i></div>
                        <h3>Collect soil data</h3>
                        <p>Use sampling grids and field readings to validate imagery-based assumptions.</p>
                    </article>
                    <article class="step">
                        <div class="step-num">04 <i class="fa-solid fa-chart-simple"></i></div>
                        <h3>Score suitability</h3>
                        <p>Compare environmental factors and generate suitability surfaces for decision review.</p>
                    </article>
                    <article class="step">
                        <div class="step-num">05 <i class="fa-solid fa-clipboard-check"></i></div>
                        <h3>Recommend action</h3>
                        <p>Export reports that summarize priority zones, evidence, and project status.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="impact">
            <div class="section-inner">
                <div class="section-grid reveal">
                    <div>
                        <div class="section-label">Why It Matters</div>
                        <h2 class="section-title">A cleaner way to protect coastlines and plan restoration work.</h2>
                    </div>
                    <p class="section-copy">
                        Instead of scattered files and manual inspection, Project MANGROVE gives stakeholders a structured view of field evidence, model outputs, and rehabilitation priorities.
                    </p>
                </div>

                <div class="metric-grid reveal">
                    <div class="metric"><strong>UAV</strong><span>Drone imagery for high-resolution coastal mapping</span></div>
                    <div class="metric"><strong>AI</strong><span>ResNet18-assisted land cover and suitability classification</span></div>
                    <div class="metric"><strong>GIS</strong><span>Spatial database workflows for maps, AOIs, and overlays</span></div>
                    <div class="metric"><strong>Ops</strong><span>Built for field-ready rehabilitation decision support</span></div>
                </div>
            </div>
        </section>

        <section class="cta-band">
            <div class="cta-inner reveal">
                <div>
                    <h2>Ready for project monitoring?</h2>
                    <p>Sign in to manage surveys, review suitability outputs, and prepare rehabilitation reports.</p>
                </div>
                <a href="{{ route('login') }}" class="button button-primary"><i class="fa-solid fa-arrow-right-to-bracket"></i> Login</a>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-top">
            <div class="footer-col footer-brand-col">
                <a href="{{ route('landing') }}" class="footer-brand">
                    <span class="footer-brand-mark"><img src="{{ asset('images/logo/ProjMangrove_Logo.svg') }}" alt="Project MANGROVE logo"></span>
                    <span class="footer-brand-name">Project MANGROVE</span>
                </a>
                <p class="footer-tagline">Environmental monitoring and spatial decision support for mangrove rehabilitation.</p>
                <div class="footer-socials">
                    <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" aria-label="X (Twitter)"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
                    <a href="mailto:info@projectmangrove.ph" aria-label="Email"><i class="fa-solid fa-envelope"></i></a>
                </div>
            </div>

            <div class="footer-col">
                <h4>Platform</h4>
                <a href="#home">Home</a>
                <a href="#capabilities">Capabilities</a>
                <a href="#fieldwork">Fieldwork</a>
                <a href="#workflow">Workflow</a>
            </div>

            <div class="footer-col">
                <h4>Resources</h4>
                <a href="#">Documentation</a>
                <a href="#">Field Guides</a>
                <a href="#">Data Standards</a>
                <a href="#">FAQ</a>
            </div>

            <div class="footer-col">
                <h4>Organization</h4>
                <p>SPAMAST Malita Campus<br>BS Information Technology</p>
                <a href="mailto:info@projectmangrove.ph"><i class="fa-solid fa-envelope"></i> info@projectmangrove.ph</a>
            </div>
        </div>

        <div class="footer-divider"></div>

        <div class="footer-bottom">
            <span>&copy; {{ date('Y') }} Project MANGROVE. All rights reserved.</span>
            <div class="footer-legal">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
            </div>
            <div class="credits">
                Photo credits:
                <a href="https://commons.wikimedia.org/wiki/File:Mangrove_Forest_in_Metro_Manila,_Philippines.jpg">FroyR</a>,
                <a href="https://commons.wikimedia.org/wiki/File:Mangrove_Plantation.jpg">Mikodelfin</a>
                via Wikimedia Commons, CC BY-SA 4.0.
            </div>
        </div>
    </footer>

    <script>
        const navbar = document.getElementById("navbar");
        window.addEventListener("scroll", () => {
            navbar.classList.toggle("scrolled", window.scrollY > 40);
        });

        const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

        // Scroll-triggered reveal animations
        const revealEls = document.querySelectorAll(".reveal");
        if (prefersReducedMotion) {
            revealEls.forEach((el) => el.classList.add("is-visible"));
        } else {
            const revealObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("is-visible");
                        revealObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15, rootMargin: "0px 0px -40px 0px" });
            revealEls.forEach((el) => revealObserver.observe(el));
        }

        // Hero background slideshow
        if (!prefersReducedMotion) {
            const heroSlides = document.querySelectorAll("#heroBgLayer .hero-bg");
            if (heroSlides.length > 1) {
                let activeSlide = 0;
                setInterval(() => {
                    heroSlides[activeSlide].classList.remove("is-active");
                    activeSlide = (activeSlide + 1) % heroSlides.length;
                    heroSlides[activeSlide].classList.add("is-active");
                }, 6000);
            }
        }
    </script>
</body>
</html>
