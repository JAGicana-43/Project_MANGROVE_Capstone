@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
<div class="page-header">
    <div>
        <h1>Settings</h1>
        <p>Feature toggles for the whole system — changes apply immediately for every user.</p>
    </div>
</div>

@if (session('success'))
    <div class="settings-alert">{{ session('success') }}</div>
@endif

<div class="glass-panel settings-card">
    @foreach ($toggles as $toggle)
        <div class="settings-row">
            <div class="settings-row-copy">
                <h3>{{ $toggle['label'] }}</h3>
                <p>{{ $toggle['description'] }}</p>
            </div>
            <form method="POST" action="{{ route('admin.settings.update') }}" class="settings-toggle-form">
                @csrf
                <input type="hidden" name="key" value="{{ $toggle['key'] }}">
                <input type="hidden" name="value" value="{{ $toggle['value'] ? '0' : '1' }}">
                <button type="submit" class="settings-switch {{ $toggle['value'] ? 'is-on' : '' }}" aria-pressed="{{ $toggle['value'] ? 'true' : 'false' }}" title="Toggle {{ $toggle['label'] }}">
                    <span class="settings-switch-knob"></span>
                </button>
            </form>
        </div>
    @endforeach
</div>

<style>
    .settings-alert { background: rgba(45,106,79,0.1); color: #2D6A4F; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-weight: 600; font-size: 14px; }
    .settings-card { padding: 8px 24px; }
    .settings-row { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 20px 0; border-bottom: 1px solid rgba(120,120,120,0.15); }
    .settings-row:last-child { border-bottom: none; }
    .settings-row-copy h3 { font-size: 15px; margin-bottom: 6px; }
    .settings-row-copy p { font-size: 13px; color: var(--text-muted, #6c757d); max-width: 560px; line-height: 1.5; }
    .settings-toggle-form { flex-shrink: 0; }
    .settings-switch { width: 46px; height: 26px; border-radius: 999px; border: none; background: rgba(120,120,120,0.35); position: relative; cursor: pointer; padding: 0; transition: background 0.2s ease; }
    .settings-switch.is-on { background: #2D6A4F; }
    .settings-switch-knob { position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 50%; background: #fff; transition: transform 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }
    .settings-switch.is-on .settings-switch-knob { transform: translateX(20px); }
</style>
@endsection
