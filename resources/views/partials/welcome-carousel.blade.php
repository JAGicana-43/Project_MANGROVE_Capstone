{{-- First-login welcome tour for researchers. Gated by $user->needsWelcomeTour() at the include site. --}}
<div class="tour-backdrop" id="tourBackdrop"></div>

<div class="tour-modal" id="tourModal" role="dialog" aria-modal="true" aria-label="Welcome to Project M.A.N.G.R.O.V.E">
    <button type="button" class="tour-close" id="tourCloseBtn" title="Skip tour" aria-label="Skip tour">
        <i class="fa-solid fa-xmark"></i>
    </button>

    <div class="tour-slides" id="tourSlides">

        {{-- Screen 1 — Welcome --}}
        <section class="tour-slide" data-slide="0">
            <div class="tour-visual tour-visual--photo" style="background-image: url('{{ asset('images/landing/mangrove-forest-philippines.jpg') }}')">
                <div class="tour-photo-gradient"></div>
            </div>
            <div class="tour-copy">
                <div class="tour-eyebrow"><i class="fa-solid fa-seedling"></i> Getting started</div>
                <h2>Welcome to Project M.A.N.G.R.O.V.E</h2>
                <p>A GIS platform that connects drone surveys, orthomosaic analysis, and field soil sampling into one guided workflow — built for mangrove rehabilitation planning.</p>
            </div>
        </section>

        {{-- Screen 2 — Define your site --}}
        <section class="tour-slide" data-slide="1">
            <div class="tour-visual tour-visual--map">
                @include('partials.tour-map-base')
                <svg class="tour-overlay" viewBox="0 0 400 220" preserveAspectRatio="xMidYMid meet">
                    <polygon points="120,60 230,50 275,110 240,165 150,170 100,120" class="tour-aoi-outline"/>
                    <circle cx="120" cy="60" r="5" class="tour-vertex"/>
                    <circle cx="230" cy="50" r="5" class="tour-vertex"/>
                    <circle cx="275" cy="110" r="5" class="tour-vertex"/>
                    <circle cx="240" cy="165" r="5" class="tour-vertex"/>
                    <circle cx="150" cy="170" r="5" class="tour-vertex"/>
                    <circle cx="100" cy="120" r="5" class="tour-vertex"/>
                </svg>
                <span class="tour-badge tour-badge--aoi"><i class="fa-solid fa-draw-polygon"></i> AOI boundary</span>
            </div>
            <div class="tour-copy">
                <div class="tour-eyebrow"><i class="fa-solid fa-map-location-dot"></i> Step 1</div>
                <h2>Start by drawing your Area of Interest</h2>
                <p>Mark the boundary of your rehabilitation site directly on the map. This defines the scope for everything that follows — flight planning, image analysis, and sampling.</p>
            </div>
        </section>

        {{-- Screen 3 — Plan the drone survey --}}
        <section class="tour-slide" data-slide="2">
            <div class="tour-visual tour-visual--map">
                @include('partials.tour-map-base')
                <svg class="tour-overlay" viewBox="0 0 400 220" preserveAspectRatio="xMidYMid meet">
                    <polyline points="115,70 115,155 155,155 155,75 195,75 195,150 235,150 235,80 265,80" class="tour-flight-path"/>
                    <g class="tour-waypoints">
                        <circle cx="115" cy="70" r="4"/><circle cx="115" cy="112" r="4"/><circle cx="115" cy="155" r="4"/>
                        <circle cx="155" cy="155" r="4"/><circle cx="155" cy="112" r="4"/><circle cx="155" cy="75" r="4"/>
                        <circle cx="195" cy="75" r="4"/><circle cx="195" cy="112" r="4"/><circle cx="195" cy="150" r="4"/>
                        <circle cx="235" cy="150" r="4"/><circle cx="235" cy="112" r="4"/><circle cx="235" cy="80" r="4"/>
                        <circle cx="265" cy="80" r="4"/>
                    </g>
                </svg>
                <span class="tour-badge tour-badge--mission"><i class="fa-solid fa-paper-plane"></i> Mission ready</span>
            </div>
            <div class="tour-copy">
                <div class="tour-eyebrow"><i class="fa-solid fa-route"></i> Step 2</div>
                <h2>Get a ready-to-fly mission, automatically</h2>
                <p>Once your area is set, we generate GPS waypoints for full aerial coverage. Download the mission file and load it into your drone's flight software — no manual route planning.</p>
            </div>
        </section>

        {{-- Screen 4 — Upload and analyze --}}
        <section class="tour-slide" data-slide="3">
            <div class="tour-visual tour-visual--grid">
                <div class="tour-tilegrid">
                    <span class="tour-tile tour-tile--dense"></span><span class="tour-tile tour-tile--dense"></span><span class="tour-tile tour-tile--dense"></span><span class="tour-tile tour-tile--soil"></span><span class="tour-tile tour-tile--dense"></span><span class="tour-tile tour-tile--dense"></span>
                    <span class="tour-tile tour-tile--dense"></span><span class="tour-tile tour-tile--fringe"></span><span class="tour-tile tour-tile--dense"></span><span class="tour-tile tour-tile--dense"></span><span class="tour-tile tour-tile--soil"></span><span class="tour-tile tour-tile--dense"></span>
                    <span class="tour-tile tour-tile--fringe"></span><span class="tour-tile tour-tile--fringe"></span><span class="tour-tile tour-tile--mudflat"></span><span class="tour-tile tour-tile--fringe"></span><span class="tour-tile tour-tile--fringe"></span><span class="tour-tile tour-tile--water"></span>
                    <span class="tour-tile tour-tile--water"></span><span class="tour-tile tour-tile--mudflat"></span><span class="tour-tile tour-tile--water"></span><span class="tour-tile tour-tile--water"></span><span class="tour-tile tour-tile--mudflat"></span><span class="tour-tile tour-tile--water"></span>
                </div>
                <div class="tour-legend">
                    <span><i class="tour-swatch tour-swatch--water"></i> Open water</span>
                    <span><i class="tour-swatch tour-swatch--mudflat"></i> Mudflat</span>
                    <span><i class="tour-swatch tour-swatch--fringe"></i> Mangrove fringe</span>
                    <span><i class="tour-swatch tour-swatch--dense"></i> Dense vegetation</span>
                    <span><i class="tour-swatch tour-swatch--soil"></i> Bare soil</span>
                </div>
            </div>
            <div class="tour-copy">
                <div class="tour-eyebrow"><i class="fa-solid fa-brain"></i> Step 3</div>
                <h2>Turn imagery into insight</h2>
                <p>After your survey is flown and stitched into an orthomosaic, upload it here. Our model classifies land cover — open water, mudflat, mangrove fringe, dense vegetation, bare soil — tile by tile.</p>
            </div>
        </section>

        {{-- Screen 5 — See where to sample --}}
        <section class="tour-slide" data-slide="4">
            <div class="tour-visual tour-visual--grid">
                <div class="tour-tilegrid tour-tilegrid--heat">
                    <span class="tour-tile tour-tile--low"></span><span class="tour-tile tour-tile--low"></span><span class="tour-tile tour-tile--low"></span><span class="tour-tile tour-tile--mid"></span><span class="tour-tile tour-tile--low"></span><span class="tour-tile tour-tile--low"></span>
                    <span class="tour-tile tour-tile--mid"></span><span class="tour-tile tour-tile--high"><i class="fa-solid fa-location-dot tour-pin"></i></span><span class="tour-tile tour-tile--mid"></span><span class="tour-tile tour-tile--low"></span><span class="tour-tile tour-tile--mid"></span><span class="tour-tile tour-tile--low"></span>
                    <span class="tour-tile tour-tile--high"><i class="fa-solid fa-location-dot tour-pin"></i></span><span class="tour-tile tour-tile--mid"></span><span class="tour-tile tour-tile--mid"></span><span class="tour-tile tour-tile--high"><i class="fa-solid fa-location-dot tour-pin"></i></span><span class="tour-tile tour-tile--mid"></span><span class="tour-tile tour-tile--low"></span>
                    <span class="tour-tile tour-tile--low"></span><span class="tour-tile tour-tile--mid"></span><span class="tour-tile tour-tile--low"></span><span class="tour-tile tour-tile--low"></span><span class="tour-tile tour-tile--mid"></span><span class="tour-tile tour-tile--low"></span>
                </div>
                <div class="tour-legend tour-legend--gradient">
                    <span class="tour-gradient-bar"></span>
                    <span class="tour-gradient-labels"><span>Low priority</span><span>High priority</span></span>
                </div>
            </div>
            <div class="tour-copy">
                <div class="tour-eyebrow"><i class="fa-solid fa-chart-simple"></i> Step 4</div>
                <h2>Suitability scores, mapped for you</h2>
                <p>Classified tiles are scored and ranked. The highest-priority grid cells become GPS sampling points, sent straight to your field researcher's app — no arbitrary guesswork.</p>
            </div>
        </section>

        {{-- Screen 6 — Track results in the field --}}
        <section class="tour-slide" data-slide="5">
            <div class="tour-visual tour-visual--map">
                @include('partials.tour-map-base')
                <div class="tour-field-pins">
                    <i class="fa-solid fa-location-dot tour-fieldpin" style="left: 22%; top: 55%;"></i>
                    <i class="fa-solid fa-location-dot tour-fieldpin" style="left: 76%; top: 60%;"></i>
                    <i class="fa-solid fa-location-dot tour-fieldpin tour-fieldpin--active" style="left: 48%; top: 72%;"></i>
                </div>
                <div class="tour-popup" style="left: 48%; top: 72%;">
                    <div class="tour-popup-row"><span>pH</span><strong>6.8</strong></div>
                    <div class="tour-popup-row"><span>Salinity</span><strong>18 ppt</strong></div>
                    <div class="tour-popup-row"><span>Moisture</span><strong>42%</strong></div>
                    <div class="tour-popup-row"><span>NPK</span><strong>12-8-10</strong></div>
                </div>
            </div>
            <div class="tour-copy">
                <div class="tour-eyebrow"><i class="fa-solid fa-flask-vial"></i> Step 5</div>
                <h2>Watch soil data arrive in real time</h2>
                <p>As your field researcher collects pH, salinity, moisture, and NPK readings, they sync here — geotagged and timestamped, plotted right on your map.</p>
            </div>
        </section>

        {{-- Screen 7 — You're set --}}
        <section class="tour-slide" data-slide="6">
            <div class="tour-visual tour-visual--photo" style="background-image: url('{{ asset('images/landing/mangrove-plantation-philippines.jpg') }}')">
                <div class="tour-photo-gradient"></div>
            </div>
            <div class="tour-copy">
                <div class="tour-eyebrow"><i class="fa-solid fa-clipboard-check"></i> You're all set</div>
                <h2>From site to report, all in one place</h2>
                <p>Compare surveys over time, review your dashboard, and generate downloadable reports for rehabilitation planning.</p>
            </div>
        </section>

    </div>

    <div class="tour-footer">
        <div class="tour-footer-main" id="tourFooterMain">
            <div class="tour-dots" id="tourDots">
                @for ($i = 0; $i < 7; $i++)
                    <button type="button" class="tour-dot {{ $i === 0 ? 'is-active' : '' }}" data-goto="{{ $i }}" aria-label="Go to screen {{ $i + 1 }}"></button>
                @endfor
            </div>

            <div class="tour-actions" id="tourActionsDefault">
                <button type="button" class="btn-secondary" id="tourSkipBtn">Skip</button>
                <button type="button" class="btn-primary" id="tourNextBtn">Next <i class="fa-solid fa-arrow-right"></i></button>
            </div>

            <div class="tour-actions tour-actions--final" id="tourActionsFinal" style="display:none;">
                <button type="button" class="btn-secondary" id="tourExploreBtn">Explore on my own</button>
                <a href="{{ route('projects.create') }}" class="btn-primary" id="tourCreateBtn">
                    <i class="fa-solid fa-plus"></i> Create your first project
                </a>
            </div>
        </div>

        <div class="tour-footer-confirm" id="tourFooterConfirm" style="display:none;">
            <p><i class="fa-solid fa-circle-question"></i> Skip the tour? You can't reopen it later.</p>
            <div class="tour-actions">
                <button type="button" class="btn-secondary" id="tourConfirmCancelBtn">Never mind</button>
                <button type="button" class="btn-primary" id="tourConfirmSkipBtn">Yes, skip</button>
            </div>
        </div>
    </div>
</div>

<style>
    .tour-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 2000;
        background: rgba(11, 22, 18, 0.55);
        backdrop-filter: blur(3px);
    }

    .tour-modal {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 2100;
        width: min(760px, calc(100vw - 32px));
        max-height: calc(100vh - 48px);
        overflow: hidden;
        background: var(--surface);
        border-radius: 16px;
        box-shadow: 0 30px 80px rgba(8, 20, 15, 0.35);
        display: flex;
        flex-direction: column;
    }

    .tour-backdrop.is-open, .tour-modal.is-open { display: flex; }
    .tour-backdrop.is-open { display: block; }

    .tour-close {
        position: absolute;
        top: 14px;
        right: 14px;
        z-index: 5;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.85);
        color: var(--dark-gray);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: none;
        font-size: 13px;
    }

    .tour-close:hover { background: #fff; }

    .tour-slides {
        position: relative;
        flex: 1;
        min-height: 420px;
    }

    .tour-slide {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        opacity: 0;
        visibility: hidden;
        transform: translateX(14px);
        transition: opacity 0.35s ease, transform 0.35s ease;
    }

    .tour-slide.is-active {
        opacity: 1;
        visibility: visible;
        transform: translateX(0);
        position: relative;
    }

    .tour-visual {
        position: relative;
        height: 230px;
        flex-shrink: 0;
        overflow: hidden;
        background: linear-gradient(180deg, #EAF4EF, #F8F9FA);
    }

    .tour-visual--photo {
        background-size: cover;
        background-position: center;
    }

    .tour-photo-gradient {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(8, 28, 21, 0.05) 0%, rgba(8, 28, 21, 0.55) 100%);
    }

    .tour-visual--map { }

    .tour-visual--grid {
        display: flex;
        flex-direction: column;
    }

    .tour-map-svg, .tour-overlay {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
    }

    .tour-map-land { fill: rgba(45, 106, 79, 0.16); }
    .tour-map-water { fill: rgba(64, 145, 108, 0.24); }

    .tour-aoi-outline {
        fill: rgba(116, 198, 157, 0.18);
        stroke: var(--forest-green);
        stroke-width: 2.5;
        stroke-dasharray: 7 6;
    }

    .tour-vertex {
        fill: #fff;
        stroke: var(--forest-green);
        stroke-width: 2;
    }

    .tour-badge {
        position: absolute;
        left: 14px;
        bottom: 14px;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: rgba(11, 22, 18, 0.78);
        color: #fff;
        font-size: 11.5px;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 20px;
        backdrop-filter: blur(4px);
    }

    .tour-flight-path {
        fill: none;
        stroke: var(--teal);
        stroke-width: 2;
        stroke-dasharray: 5 5;
    }

    .tour-waypoints circle {
        fill: #fff;
        stroke: var(--teal);
        stroke-width: 2;
    }

    /* Tile grid (classification + heatmap screens) */
    .tour-tilegrid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        grid-template-rows: repeat(4, 1fr);
        gap: 4px;
        flex: 1;
        padding: 16px 16px 8px;
    }

    .tour-tile {
        border-radius: 4px;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .tour-tile--water { background: #3B82F6; }
    .tour-tile--mudflat { background: #C8874A; }
    .tour-tile--fringe { background: #9AD8B8; }
    .tour-tile--dense { background: #1f5f46; }
    .tour-tile--soil { background: #8B5E3C; }

    .tour-tile--low { background: #E74C3C; }
    .tour-tile--mid { background: #F1C40F; }
    .tour-tile--high { background: #2D6A4F; }

    .tour-pin {
        color: #fff;
        font-size: 13px;
        filter: drop-shadow(0 1px 2px rgba(0,0,0,0.35));
    }

    .tour-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 14px;
        padding: 4px 16px 12px;
        font-size: 11.5px;
        color: var(--text-muted);
    }

    .tour-legend span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .tour-swatch {
        width: 10px;
        height: 10px;
        border-radius: 3px;
        display: inline-block;
    }

    .tour-swatch--water { background: #3B82F6; }
    .tour-swatch--mudflat { background: #C8874A; }
    .tour-swatch--fringe { background: #9AD8B8; }
    .tour-swatch--dense { background: #1f5f46; }
    .tour-swatch--soil { background: #8B5E3C; }

    .tour-legend--gradient {
        flex-direction: column;
        align-items: stretch;
        gap: 6px;
    }

    .tour-gradient-bar {
        height: 8px;
        border-radius: 6px;
        background: linear-gradient(90deg, #E74C3C, #F1C40F, #2D6A4F);
    }

    .tour-gradient-labels {
        display: flex;
        justify-content: space-between;
        font-size: 11px;
    }

    /* Field pins + popup */
    .tour-field-pins { position: absolute; inset: 0; }

    .tour-fieldpin {
        position: absolute;
        transform: translate(-50%, -100%);
        color: var(--text-muted);
        font-size: 20px;
        filter: drop-shadow(0 2px 3px rgba(0,0,0,0.2));
    }

    .tour-fieldpin--active { color: var(--forest-green); font-size: 24px; }

    .tour-popup {
        position: absolute;
        transform: translate(-50%, calc(-100% - 14px));
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 10px 26px rgba(8, 20, 15, 0.22);
        padding: 8px 12px;
        min-width: 128px;
        font-size: 11.5px;
    }

    .tour-popup-row {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        padding: 2px 0;
        color: var(--text-muted);
    }

    .tour-popup-row strong { color: var(--dark-gray); }

    .tour-copy {
        padding: 22px 28px 8px;
    }

    .tour-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: var(--accent-green);
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-bottom: 8px;
    }

    .tour-copy h2 {
        font-family: 'Poppins', sans-serif;
        font-size: 22px;
        color: var(--heading-accent);
        margin-bottom: 8px;
        line-height: 1.25;
    }

    .tour-copy p {
        color: var(--text-muted);
        font-size: 14px;
        line-height: 1.55;
    }

    .tour-footer {
        padding: 16px 28px 22px;
        margin-top: auto;
    }

    .tour-footer-main {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .tour-footer-confirm {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .tour-footer-confirm p {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--dark-gray);
        font-size: 13.5px;
        font-weight: 500;
    }

    .tour-footer-confirm p i {
        color: var(--text-muted);
    }

    .tour-dots {
        display: flex;
        gap: 7px;
    }

    .tour-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--border-soft);
        padding: 0;
        transition: background 0.2s ease, transform 0.2s ease;
    }

    .tour-dot.is-active {
        background: var(--forest-green);
        transform: scale(1.25);
    }

    .tour-actions, .tour-actions--final {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    @media (max-width: 640px) {
        .tour-modal { max-height: calc(100vh - 24px); }
        .tour-visual { height: 180px; }
        .tour-copy { padding: 18px 20px 6px; }
        .tour-footer { padding: 14px 20px 18px; }
        .tour-footer-main, .tour-footer-confirm { flex-direction: column; align-items: stretch; }
        .tour-dots { justify-content: center; order: 2; }
        .tour-actions, .tour-actions--final { order: 1; justify-content: space-between; }
        .tour-footer-confirm p { justify-content: center; text-align: center; }
    }
</style>

<script>
(function () {
    var totalSlides = 7;
    var currentSlide = 0;

    var backdrop = document.getElementById('tourBackdrop');
    var modal = document.getElementById('tourModal');
    var slides = document.querySelectorAll('#tourSlides .tour-slide');
    var dots = document.querySelectorAll('#tourDots .tour-dot');
    var actionsDefault = document.getElementById('tourActionsDefault');
    var actionsFinal = document.getElementById('tourActionsFinal');
    var footerMain = document.getElementById('tourFooterMain');
    var footerConfirm = document.getElementById('tourFooterConfirm');
    var confirmingSkip = false;

    function render() {
        slides.forEach(function (slide) {
            slide.classList.toggle('is-active', Number(slide.dataset.slide) === currentSlide);
        });
        dots.forEach(function (dot, i) {
            dot.classList.toggle('is-active', i === currentSlide);
        });

        if (footerMain) footerMain.style.display = confirmingSkip ? 'none' : 'flex';
        if (footerConfirm) footerConfirm.style.display = confirmingSkip ? 'flex' : 'none';
        if (confirmingSkip) return;

        var isLast = currentSlide === totalSlides - 1;
        if (actionsDefault) actionsDefault.style.display = isLast ? 'none' : 'flex';
        if (actionsFinal) actionsFinal.style.display = isLast ? 'flex' : 'none';
    }

    function askSkipConfirmation() {
        confirmingSkip = true;
        render();
    }

    function cancelSkipConfirmation() {
        confirmingSkip = false;
        render();
    }

    function goTo(index) {
        currentSlide = Math.max(0, Math.min(totalSlides - 1, index));
        render();
    }

    function open() {
        if (backdrop) backdrop.classList.add('is-open');
        if (modal) modal.classList.add('is-open');
        render();
    }

    function completeOnboarding() {
        if (backdrop) backdrop.classList.remove('is-open');
        if (modal) modal.classList.remove('is-open');

        fetch('{{ route('researcher.onboarding.complete') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
    }

    document.getElementById('tourNextBtn')?.addEventListener('click', function () {
        goTo(currentSlide + 1);
    });

    document.getElementById('tourSkipBtn')?.addEventListener('click', askSkipConfirmation);
    document.getElementById('tourCloseBtn')?.addEventListener('click', askSkipConfirmation);
    document.getElementById('tourConfirmSkipBtn')?.addEventListener('click', completeOnboarding);
    document.getElementById('tourConfirmCancelBtn')?.addEventListener('click', cancelSkipConfirmation);
    document.getElementById('tourExploreBtn')?.addEventListener('click', completeOnboarding);
    document.getElementById('tourCreateBtn')?.addEventListener('click', completeOnboarding);

    dots.forEach(function (dot) {
        dot.addEventListener('click', function () { goTo(Number(dot.dataset.goto)); });
    });

    document.addEventListener('DOMContentLoaded', open);
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        open();
    }
})();
</script>
