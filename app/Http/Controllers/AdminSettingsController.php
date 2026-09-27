<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    /**
     * Boolean feature toggles editable from the admin console. New toggles
     * only need an entry here + a check at the point they gate — no
     * migration, since Setting is a flat key/value store.
     */
    public const TOGGLES = [
        'show_advanced_mission_settings' => [
            'label' => 'Advanced mission settings',
            'description' => 'Show the finish-action and RC-signal-lost-action controls in the '
                . 'Mission Parameters panel. Meant to stay on during field testing so behavior can be '
                . 'tuned per flight, then turned off once the right defaults are locked in for deployment.',
            'default' => true,
        ],
        'restrict_map_to_davao_occidental' => [
            'label' => 'Restrict map to Davao Occidental',
            'description' => 'Keep the workspace map, AOI drawing, and location search locked to Davao '
                . 'Occidental (this system\'s actual deployment area). Turn off to draw an AOI and upload/'
                . 'analyze an orthomosaic anywhere in the world — needed when testing with sample imagery '
                . 'from outside the province. Should stay on for real deployment.',
            'default' => true,
        ],
        'lock_map_to_aoi' => [
            'label' => 'Lock map to confirmed AOI',
            'description' => 'Once an AOI is confirmed, keep the map boxed in around it so users stay '
                . 'focused on their work area. Turn off if an uploaded orthomosaic\'s real location doesn\'t '
                . 'overlap the AOI (e.g. test imagery from elsewhere) and the map needs to pan there to '
                . 'show it. Should stay on for real deployment.',
            'default' => true,
        ],
    ];

    public function index()
    {
        $toggles = collect(self::TOGGLES)->map(function (array $meta, string $key) {
            return $meta + ['key' => $key, 'value' => Setting::get($key, $meta['default'])];
        })->values();

        $adminNavRoute = 'settings';
        $adminPendingCount = \App\Models\User::where('status', 'pending')->count();

        return view('admin.settings', compact('toggles', 'adminNavRoute', 'adminPendingCount'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'in:' . implode(',', array_keys(self::TOGGLES))],
            'value' => ['required', 'boolean'],
        ]);

        Setting::set($validated['key'], $validated['value']);

        // record_id is a uuid column -- app_settings is a flat string-keyed
        // store (no uuid id of its own), so the key goes in the payload
        // instead of being forced into a column it doesn't fit.
        AuditLog::record('updated_setting', 'app_settings', null, [
            'key' => $validated['key'],
            'value' => $validated['value'],
        ]);

        return back()->with('success', self::TOGGLES[$validated['key']]['label'] . ' updated.');
    }
}
