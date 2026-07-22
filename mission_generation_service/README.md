# Mission Generation Service (Python/Flask)

Fresh rehaul of AOI → drone mission KMZ generation for Project MANGROVE, per
`mission_generation_prompt_1.md`. Written from scratch — not a patch on the
existing PHP implementation in `app/Services/MissionGridGenerator.php` /
`app/Services/WpmlKmzBuilder.php`. Those files are untouched.

**Not wired into the Laravel app.** This is a standalone module + local test
harness, meant to be run and validated on its own before any integration
work (an HTTP call from Laravel, auth, DB rows, the Blade form) happens.
That wiring is a deliberately separate next step.

**Test drone: DJI Mini 3.** This matters for validation strategy — DJI
Pilot 2 (the "import a KMZ with no drone needed" app-level check) does not
run on Mini-series hardware. The Mini 3 uses DJI Fly, which has no built-in
waypoint/KMZ import feature at all. See `drone_profiles.py` and the
validation-coverage section below for what that means for testing this
aircraft in practice.

## Layout

| File | Purpose |
|---|---|
| `drone_profiles.py` | Per-drone-model constant lookup (enum values, camera FOV/sensor size, altitude, speed, gimbal pitch, transitional speed). Add a model = add a dict entry. |
| `geometry.py` | Local meter-space projection, minimum-area-bounding-rectangle dominant-axis detection, rotation helpers, self-intersection check. |
| `validation.py` | AOI validation — area cap, dimension cap, self-intersection. Runs before generation. |
| `waypoint_generator.py` | Rotates the AOI to its dominant axis, sweeps boustrophedon lines, clips to the polygon, rotates back, computes real headings. |
| `kmz_builder.py` | Builds `wpmz/template.kml` + `wpmz/waylines.wpml`, zipped as `.kmz`, matching the confirmed WaypointMap export conventions. |
| `pipeline.py` | `generate_mission(...)` — the single entry point: validate → generate → build. |
| `app.py` | Minimal Flask wrapper around the pipeline (`POST /generate`). Not deployed/wired anywhere yet. |
| `tests/` | pytest suite — validation, rotation/generation, KMZ structure, AOI-responsiveness self-check. |
| `run_test_harness.py` | Runs the 5 required test cases end-to-end, writes real `.kmz` files to `test_output/`, prints a validation-coverage report (see below for what's actually checkable pre-flight on a Mini 3). |

## Running it

```bash
pip install -r mission_generation_service/requirements.txt

# Full pytest suite
python -m pytest mission_generation_service/tests -v

# End-to-end test-case harness (writes .kmz files to test_output/)
python -m mission_generation_service.run_test_harness
```

## Key design decisions (see code comments for the full reasoning)

- **Rotation-to-dominant-axis** (`geometry.dominant_axis`, requirement 2):
  flight lines are rotated to match the AOI's minimum-area-bounding-rectangle
  orientation, rather than always flying a fixed axis. **Not confirmed**
  against a real rotated-AOI WaypointMap export — neither reference KMZ had
  one. If DJI Pilot 2 / field testing shows WaypointMap actually uses a fixed
  axis, `dominant_axis` is the one function to change.
- **Dimension cap measured on the rotated rectangle's long side**
  (`validation.MAX_DIMENSION_M`), not the raw axis-aligned lat/lng bounding
  box — the latter can under/overstate a rotated AOI's true length.
- **`globalTransitionalSpeed = 2.5`**, matching the real WaypointMap export's
  value, not the old (untested) script's unexamined `3`.
- **`actionGroupId` reuse**: the old script incremented a unique action group
  ID per waypoint. The real export reuses `2` for every waypoint after the
  first. Fixed here (`kmz_builder._build_waylines_wpml`).
- **Real headings on every waypoint**, even ones with
  `waypointHeadingAngleEnable=0` — the old script defaulted disabled
  waypoints' heading to `0`; the real export computes genuine values
  regardless of the Enable flag.

## Validation coverage — what's proven vs. what still needs testing

**Confirmed automatically (no drone or app needed)** — this is what
`pytest` + `run_test_harness.py` check on every run:
- AOI area cap (25 ha), dimension cap (500 m), self-intersection rejection,
  each with a specific error message.
- The AOI actually drives the output: waypoint bounding box corresponds to
  each AOI's own input bounding box (not a fixed/default shape), and
  waypoint/line counts scale with AOI size — checked across 3 AOIs that
  differ in location, size, *and* shape (`test_aoi_responsiveness.py`).
- Generated KMZ XML structure matches the confirmed reference conventions:
  action-group ID pattern, heading-enable pattern, `distance`/`duration=0`,
  `lng,lat` coordinate order (`test_kmz_structure.py`).
- The `.kmz` is a well-formed zip with well-formed XML inside.

**Not available for a DJI Mini 3** — the "import in DJI Pilot 2, no drone
needed" check this project's original validation strategy assumed only
applies to Enterprise-series aircraft (M300/M350 RTK, M30/M30T, M3E/M3T/M3M,
M3D/M3TD). DJI Pilot 2 doesn't run on Mini-series hardware, and DJI Fly (what
the Mini 3 actually uses) has no built-in KMZ import. Getting one of these
`.kmz` files onto the Mini 3 at all currently means either:
- The unofficial workaround: create a placeholder mission on the RC, then
  manually replace that mission file on the controller's filesystem with this
  `.kmz`.
- A third-party bridge tool (e.g. MavenBridge) built specifically for this
  gap.

Neither is an official DJI import path, so treat whatever you see after
either route as the drone's own pre-flight acceptance/rejection of the file
— useful signal, but not the same guarantee DJI Pilot 2 gives on Enterprise
hardware.

**Requires an actual flight** — nothing here substitutes for these:
- Real GPS accuracy/drift in the field.
- Real battery consumption and flight time vs. this module's estimates.
- Whether the rotation-to-dominant-axis decision above actually matches how
  a real mission planner expects a rotated AOI to fly.
- Gimbal/camera behavior in wind, and real image overlap on the ground.
- RC signal loss / lost-action behavior.
- Whether the placeholder `drone_enum_value`/`drone_sub_enum_value`
  (unconfirmed — see `drone_profiles.py`) cause the aircraft to reject or
  misinterpret the mission via the file-swap workaround.
