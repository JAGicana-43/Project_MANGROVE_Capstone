<?php

// config/drones.php
//
// Registry of supported DJI drone models and the WPML enum values DJI's
// apps expect in template.kml / waylines.wpml. Add new models here as
// they're confirmed — nothing else in the codebase needs to change.
//
// To find a new model's enumValue/subEnumValue: export a mission from that
// drone's official DJI app (or WaypointMap, if it has that model listed)
// and inspect template.kml's <wpml:droneInfo> block.

return [

    // cameraFovDeg feeds directly into line-spacing math (footprint width =
    // 2 * altitude * tan(fov/2)), so it's not a cosmetic value — it changes
    // waypoint geometry, not just metadata. 82.1 deg / 9.6x7.2mm sensor are
    // DJI's published DJI Mini 3 specs (dji.com/mini-3/specs).
    //
    // globalTransitionalSpeed: the old, untested script used 3 m/s with no
    // stated reasoning. `output_FINAL.kmz` (the real WaypointMap export used
    // as the authoritative structural reference) uses 2.5 m/s. We
    // deliberately match the real export's value here rather than carry
    // forward the old script's unexamined constant. This isn't Mini-3-
    // specific — it's a WPML mission-file convention.
    //
    // enumValue/subEnumValue: UNCONFIRMED PLACEHOLDER — flagging this rather
    // than silently guessing. DJI's official WPML droneEnumValue table
    // (developer.dji.com/doc/cloud-api-tutorial) only documents
    // Enterprise-series aircraft: M300/M350 RTK, M30/M30T, M3E/M3T/M3M,
    // M3D/M3TD, M400. There is no officially published enum value for the
    // Mini series — even DJI's own SDK forum has an open, unanswered
    // question asking for the Mini 3 Pro's droneEnumValue. The values below
    // are carried over from the old generic placeholder and are NOT
    // confirmed to identify this aircraft correctly to any DJI app.
    //
    // More importantly: DJI Pilot 2 (the "import a KMZ with no drone
    // needed" validation step this project's docs assume) does not run on
    // Mini-series hardware at all — it's tied to enterprise RC hardware.
    // The Mini 3 uses DJI Fly, which has no built-in waypoint/KMZ import
    // feature. The only ways to get a WPML mission onto this aircraft are
    // unofficial: manually replacing a placeholder mission file on the RC's
    // filesystem, or a third-party bridge tool (e.g. MavenBridge). Structural
    // validation (this codebase's tests) still holds, but "confirmed by app
    // import" is not available for this aircraft the way it is for
    // Enterprise drones — real-world validation here means the file-swap
    // workaround or an actual flight, not a DJI Pilot 2 preview.
    // altitudeM/waypointSpeedMps/gimbalPitchDeg are this aircraft's suggested
    // baseline values — MissionGenerationController falls back to them when
    // a request doesn't specify its own altitude/speed/gimbal, but the
    // Mission Parameters UI lets a caller override any of them per mission.
    // cameraTriggerIntervalS and the sensor/FOV specs below remain hardware
    // constants, not mission choices.
    'dji_mini_3' => [
        'label' => 'DJI Mini 3',
        'enumValue' => 68,
        'subEnumValue' => 0,
        'cameraFovDeg' => 82.1,
        'sensorWidthMm' => 9.6,
        'sensorHeightMm' => 7.2,
        'globalTransitionalSpeed' => 2.5,
        'altitudeM' => 100.0,
        'waypointSpeedMps' => 3.0,
        'gimbalPitchDeg' => -90.0,
        'cameraTriggerIntervalS' => 2.0,
        // DJI's published rated max flight time for the Mini 3 with the
        // standard Intelligent Flight Battery (dji.com/mini-3/specs), in
        // still air at a constant 16 km/h — not a field-measured figure for
        // this specific aircraft/battery. MissionGenerationController checks
        // a mission's estimated flight time against this with a safety
        // margin, so a generated mission can't exceed what the aircraft can
        // physically complete on one battery.
        'batteryFlightTimeMinutes' => 38.0,
    ],

    // Example entries — confirm and uncomment once model is verified:
    // 'mavic_3_enterprise' => [
    //     'label' => 'DJI Mavic 3 Enterprise',
    //     'enumValue' => 68,
    //     'subEnumValue' => 0,
    // ],
    // 'matrice_30' => [
    //     'label' => 'DJI Matrice 30',
    //     'enumValue' => 67,
    //     'subEnumValue' => 0,
    // ],

];
