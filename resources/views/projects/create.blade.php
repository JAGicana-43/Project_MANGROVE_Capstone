@extends('layouts.lobby')

@section('title', 'New Project - Project M.A.N.G.R.O.V.E')

@section('content')
<div class="form-shell">
    <div class="welcome-row">
        <div class="welcome-text">
            <h1>Create Project</h1>
            <p>Add a new mangrove monitoring project to your researcher lobby.</p>
        </div>
        <a href="{{ route('researcher.lobby') }}" class="btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Lobby</span>
        </a>
    </div>

    <form method="POST" action="{{ route('projects.store') }}" class="project-form" data-loading-label="Creating project...">
        @csrf

        <label for="name">Project name</label>
        <input
            id="name"
            name="name"
            type="text"
            value="{{ old('name') }}"
            required
            autofocus
            placeholder="e.g. Davao Gulf Coastal Rehabilitation"
        >
        @error('name')
            <p class="field-error">{{ $message }}</p>
        @enderror

        <label for="description">Description</label>
        <textarea
            id="description"
            name="description"
            rows="5"
            placeholder="Optional notes about the study area, goals, or partner team."
        >{{ old('description') }}</textarea>
        @error('description')
            <p class="field-error">{{ $message }}</p>
        @enderror

        <button type="submit" class="btn-primary">
            <i class="fa-solid fa-check"></i>
            <span>Create Project</span>
        </button>
    </form>
</div>

<style>
    .form-shell {
        max-width: 760px;
    }

    .welcome-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 32px;
        flex-wrap: wrap;
    }

    .welcome-text h1 {
        font-size: 28px;
        font-weight: 600;
        color: var(--heading-accent);
        margin-bottom: 6px;
    }

    .welcome-text p {
        color: var(--text-muted);
        font-size: 15px;
    }

    .project-form {
        display: flex;
        flex-direction: column;
        gap: 10px;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        border-radius: 8px;
        padding: 24px;
    }

    .project-form label {
        font-weight: 600;
        color: var(--dark-gray);
        margin-top: 8px;
    }

    .project-form input,
    .project-form textarea {
        width: 100%;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        border-radius: 8px;
        padding: 12px 14px;
        font: inherit;
        color: var(--dark-gray);
        resize: vertical;
    }

    .project-form input:focus,
    .project-form textarea:focus {
        outline: none;
        border-color: var(--sage-green);
        box-shadow: 0 0 0 3px rgba(116, 198, 157, 0.18);
    }

    .project-form input::placeholder,
    .project-form textarea::placeholder {
        color: var(--text-muted);
        opacity: 1;
    }

    .field-error {
        color: #C0392B;
        font-size: 13px;
    }

    .project-form .btn-primary {
        width: fit-content;
        margin-top: 12px;
    }
</style>
@endsection
