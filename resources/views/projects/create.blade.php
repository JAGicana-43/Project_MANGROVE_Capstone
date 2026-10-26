@extends('layouts.lobby')

@section('title', 'New Project - Project M.A.N.G.R.O.V.E')

@section('content')

<div class="cproj-hero">
    <div class="cproj-hero-top">
        <div>
            <p class="cproj-eyebrow">Your work</p>
            <h1>Create Project</h1>
            <p class="cproj-hero-sub">Add a new mangrove monitoring project to your researcher lobby.</p>
        </div>
        <a href="{{ route('researcher.lobby') }}" class="cproj-hero-btn">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Lobby</span>
        </a>
    </div>
</div>

<div class="cproj-layout">
    <form method="POST" action="{{ route('projects.store') }}" class="cproj-form" data-loading-label="Creating project...">
        @csrf

        <label for="name">
            <i class="fa-solid fa-signature"></i>
            Project name
        </label>
        <input
            id="name"
            name="name"
            type="text"
            value="{{ old('name') }}"
            required
            autofocus
            maxlength="255"
            placeholder="e.g. Davao Gulf Coastal Rehabilitation"
        >
        @error('name')
            <p class="field-error">{{ $message }}</p>
        @enderror

        <label for="description">
            <i class="fa-solid fa-align-left"></i>
            Description <span class="cproj-optional">Optional</span>
        </label>
        <textarea
            id="description"
            name="description"
            rows="6"
            maxlength="2000"
            placeholder="Notes about the study area, goals, or partner team."
        >{{ old('description') }}</textarea>
        @error('description')
            <p class="field-error">{{ $message }}</p>
        @enderror

        <button type="submit" class="cproj-submit">
            <i class="fa-solid fa-check"></i>
            <span>Create Project</span>
        </button>
    </form>

    <aside class="cproj-guide">
        <p class="cproj-guide-eyebrow">What happens next</p>
        <ol class="cproj-guide-steps">
            <li>
                <span class="cproj-guide-num">1</span>
                <div><b>Draw an area &amp; request a mission</b><span>Trace the boundary you want surveyed, then request the drone flight — both happen on the same step.</span></div>
            </li>
            <li>
                <span class="cproj-guide-num">2</span>
                <div><b>Upload &amp; classify the orthomosaic</b><span>Once the drone flight is done, upload the stitched imagery and run classification.</span></div>
            </li>
            <li>
                <span class="cproj-guide-num">3</span>
                <div><b>Assign sampling points</b><span>The highest-suitability cells become GPS points your field team can collect.</span></div>
            </li>
        </ol>
        <div class="cproj-guide-note">
            <i class="fa-solid fa-circle-info"></i>
            <span>You can rename, archive, or delete this project later from the lobby's "⋯" menu.</span>
        </div>
    </aside>
</div>

<style>
    .cproj-hero {
        position: relative;
        overflow: hidden;
        margin: -40px -40px 28px;
        padding: 32px 40px 26px;
        background: linear-gradient(135deg, var(--forest-green) 0%, var(--forest-green-dark) 100%);
        color: #fff;
    }

    .cproj-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        pointer-events: none;
        opacity: .5;
        background-image:
            repeating-radial-gradient(circle at 8% 20%, transparent 0, transparent 24px, rgba(255,255,255,.05) 25px, transparent 26px),
            repeating-radial-gradient(circle at 96% 85%, transparent 0, transparent 30px, rgba(255,255,255,.05) 31px, transparent 32px);
        background-size: 380px 380px, 480px 480px;
    }

    .cproj-hero-top {
        position: relative;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .cproj-eyebrow {
        margin: 0;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, .68);
    }

    .cproj-hero-top h1 {
        margin-top: 4px;
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 27px;
        color: #fff;
    }

    .cproj-hero-sub {
        margin-top: 5px;
        font-size: 13px;
        color: rgba(255, 255, 255, .78);
    }

    .cproj-hero-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border-radius: 10px;
        background: rgba(255, 255, 255, .14);
        border: 1px solid rgba(255, 255, 255, .28);
        font-size: 13px;
        font-weight: 600;
        color: #fff;
        text-decoration: none;
        transition: transform 0.14s ease, background-color 0.2s ease;
    }

    .cproj-hero-btn:hover {
        background: rgba(255, 255, 255, .22);
    }

    .cproj-hero-btn:active {
        transform: scale(0.96);
    }

    .cproj-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
        gap: 20px;
        align-items: start;
        max-width: 980px;
    }

    @media (max-width: 760px) {
        .cproj-layout {
            grid-template-columns: 1fr;
        }
    }

    .cproj-form {
        display: flex;
        flex-direction: column;
        gap: 8px;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        border-radius: 16px;
        box-shadow: var(--shadow-resting, 0 1px 2px hsl(var(--shadow-color) / 0.06), 0 3px 10px hsl(var(--shadow-color) / 0.06));
        padding: 26px;
    }

    .cproj-form label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        font-size: 13.5px;
        color: var(--dark-gray);
        margin-top: 10px;
    }

    .cproj-form label i {
        font-size: 12px;
        color: var(--sage-green);
        width: 14px;
    }

    .cproj-optional {
        margin-left: auto;
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: .03em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .cproj-form input,
    .cproj-form textarea {
        width: 100%;
        background: var(--off-white);
        border: 1px solid var(--border-soft);
        border-radius: 10px;
        padding: 12px 14px;
        font: inherit;
        font-size: 13.5px;
        color: var(--dark-gray);
        resize: vertical;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .cproj-form input:focus,
    .cproj-form textarea:focus {
        outline: none;
        border-color: var(--sage-green);
        background: var(--surface);
        box-shadow: 0 0 0 3px rgba(116, 198, 157, 0.18);
    }

    .cproj-form input::placeholder,
    .cproj-form textarea::placeholder {
        color: var(--text-muted);
        opacity: 1;
    }

    .field-error {
        color: #C0392B;
        font-size: 12.5px;
    }

    .cproj-submit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: fit-content;
        margin-top: 18px;
        padding: 12px 22px;
        border: 0;
        border-radius: 10px;
        background: var(--forest-green);
        color: #fff;
        font-size: 13.5px;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.14s ease, background-color 0.2s ease;
    }

    .cproj-submit:hover {
        background: var(--forest-green-dark);
    }

    .cproj-submit:active {
        transform: scale(0.96);
    }

    .cproj-guide {
        background: rgba(116, 198, 157, .08);
        border: 1.5px dashed var(--sage-green);
        border-radius: 16px;
        padding: 24px;
    }

    .cproj-guide-eyebrow {
        margin: 0 0 16px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--teal);
    }

    .cproj-guide-steps {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 16px;
        margin: 0;
        padding: 0;
    }

    .cproj-guide-steps li {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .cproj-guide-num {
        flex: none;
        width: 22px;
        height: 22px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: var(--forest-green);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
    }

    .cproj-guide-steps b {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .cproj-guide-steps span {
        display: block;
        margin-top: 2px;
        font-size: 12px;
        line-height: 1.5;
        color: var(--text-muted);
    }

    .cproj-guide-note {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin-top: 18px;
        padding-top: 16px;
        border-top: 1px solid rgba(116, 198, 157, .3);
        font-size: 11.5px;
        line-height: 1.5;
        color: var(--text-muted);
    }

    .cproj-guide-note i {
        margin-top: 1px;
        color: var(--teal);
    }

    @media (max-width: 640px) {
        .cproj-hero {
            margin: -28px -16px 22px;
            padding: 24px 16px 20px;
        }
    }
</style>
@endsection
