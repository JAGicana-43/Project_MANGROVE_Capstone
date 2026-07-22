# Prompt for Claude Code — Mission Generation Rehaul (AOI → KMZ)

Copy everything below into Claude Code.

---

I'm rebuilding the **Mission Generation Module** for my capstone system, Project MANGROVE (Flask backend, Node/Express, Leaflet.js frontend, MySQL). I have a previous, untested iteration of this feature, but I want a **full rehaul, not a patch on top of it**. Don't extend or lightly refactor the old code — the old version's structure and assumptions may conflict with the corrected logic below, and I'd rather have a clean, verified rebuild than something patched together from a base I don't trust yet. You can reference the old script for what *not* to repeat, but the new module should be written fresh against the requirements below.

## Reference files (both provided)

1. **`mangrove-mission-4.kmz`** — output from the old, untested script. This is a reference for what to avoid/fix, not a base to build on.
2. **`output_FINAL.kmz`** — a real mission file exported directly from WaypointMap (a working third-party tool). Treat this as the **authoritative structural reference**. Unzip both and compare `wpmz/template.kml` and `wpmz/waylines.wpml` before writing anything.

From comparing these two, here's what's already confirmed about the correct structure — build to match `output_FINAL.kmz`'s conventions exactly:

- Two-file structure: `wpmz/template.kml` (mission-level config) + `wpmz/waylines.wpml` (waypoints), zipped as KMZ.
- Coordinates in `longitude,latitude` order (KML standard) — confirmed correct in both files, keep it that way.
- `wpml:distance` and `wpml:duration` in the Folder header are legitimately `0` in the real export — don't try to compute real values here, just match the convention.
- Action group pattern: the **first** waypoint gets two action groups (an initial absolute `gimbalRotate`, plus a `gimbalEvenlyRotate`); every waypoint after that gets exactly **one** action group (`gimbalEvenlyRotate` only). Total action groups = N waypoints + 1.
- In the real export, `actionGroupId` is reused (stays at `2`) across every subsequent waypoint's single action group, rather than incrementing per waypoint. The old script incremented a unique ID per waypoint instead — functionally probably harmless, but match the real convention unless you have a reason not to.
- `waypointHeadingAngleEnable` is `1` only on the very first waypoint, `0` on every other waypoint, in both files — keep this.
- The real export computes and stores actual heading angles (e.g. alternating values matching flight-line direction) on every waypoint even when `Enable=0` for that point. The old script just wrote `0` on all disabled waypoints. Since `Enable=0` likely means the value is ignored by the flight controller, this is low risk either way, but compute real values instead of defaulting to `0` for correctness.

## Requirements for the new logic

**1. AOI validation (runs before any generation logic)**
- Reject AOIs exceeding **2 hectares (20,000 m²)**.
- Reject AOIs exceeding a **~300m** longest bounding-box dimension (adjust if testing shows this is too strict/loose — the point is catching long thin AOIs that stay under the area cap but still blow up flight-line count).
- Reject self-intersecting polygons before they ever reach the waypoint generator.
- Return clear, specific validation error messages the frontend can display — no silent failures.

**2. Waypoint generation — make the rotation decision explicit, don't leave it ambiguous**
Neither of my two reference files has a rotated/diagonal AOI, so I don't actually know yet whether WaypointMap auto-rotates flight lines to match AOI shape, or just runs a fixed axis (e.g. always north-south) and expects the user to pick orientation manually. Given my real AOIs (mangrove strips following a coastline) are likely to be elongated and non-axis-aligned, implement **rotation-to-dominant-axis**:
- Compute the AOI polygon's dominant axis (minimum-area bounding rectangle or PCA on vertices) → rotation angle θ.
- Rotate the polygon by −θ, generate parallel lines at spacing derived from the fixed altitude/FOV/overlap, clip to the rotated polygon boundary, connect into a zigzag path, rotate waypoints back by +θ.
- Document this as a deliberate design decision in code comments (not just implemented silently) since it's not confirmed against the reference tool's actual behavior.

**3. Prove the AOI is actually driving the output — this is the most important requirement**
The old script's output couldn't be confirmed as genuinely responsive to different input AOIs — it might just be reproducing similar output regardless of what's drawn. Build in an explicit self-check: generate output from at least three distinct test AOIs (different location, size, and shape) and verify programmatically that each output's coordinate bounding box actually corresponds to its input AOI's bounding box, and that waypoint count/line count scales sensibly with AOI size. This should be a real automated check, not just "it ran without an error."

**4. Fixed flight parameters — per drone model, not globally hardcoded**
Altitude, speed, camera trigger interval, gimbal pitch, `globalTransitionalSpeed` are constants *for a given drone*, but the system needs to support multiple DJI drone models eventually (different users may fly different hardware). Don't hardcode `droneEnumValue` or camera FOV/sensor specs as single constants — structure them as a lookup table keyed by drone model (e.g. a dict/config: model name → `droneEnumValue`, `droneSubEnumValue`, camera FOV, sensor dimensions). Line spacing depends on camera FOV, so this isn't just a cosmetic parameter swap — it changes the geometry math. For now, populate the lookup with just the one model I'm currently testing with, but design the function signature to take a drone model identifier as input rather than assuming a single global constant, so adding models later is a data addition, not a code change. Confirm the constant values used for the current model are intentional (the old script used `globalTransitionalSpeed=3`; the reference file used `2.5` — pick deliberately, don't leave it as an unexamined leftover).

**5. Validation strategy given limited physical drone access**
Structure the test harness so as much as possible is verifiable without a physical drone: generated KMZ files should be import-testable in DJI Pilot 2 (app + remote controller, no drone required to preview a mission) before any real flight test. Flag clearly in the output/docs which parts of validation still require an actual flight (e.g. GPS accuracy in the field, real battery/time consumption) versus which parts are confirmed by app-level import alone.

## Test cases

1. Small axis-aligned rectangle (~0.5–1 hectare, well under the 2-hectare cap) — sanity-check the full pipeline end to end.
2. Small rotated/diagonal rectangle, same size — exercises the rotation + clip logic specifically.
3. Small irregular polygon (5–6 vertices, e.g. an L-shape) — exercises clipping on a non-trivial boundary.
4. Edge cases: a thin sliver polygon near the dimension cap, and a deliberately self-intersecting polygon (must be rejected before generation runs, not crash the generator).
5. The AOI-responsiveness self-check from requirement 3, run across all of the above.

## What I still have to verify myself (not for Claude Code to do)

Structural correctness on paper isn't the same as a working mission. Once this is built, my own next step is importing a generated KMZ into DJI Pilot 2 (or whatever the target flight app ends up being) to confirm it's actually accepted and flyable — that's the real ground truth, and no static analysis substitutes for it.

## Deliverable

A fresh Python module (Flask-compatible) implementing the pipeline above, matching `output_FINAL.kmz`'s structural conventions, with a drone-model lookup (not a single hardcoded model) and a test harness covering the five test cases above (including the AOI-responsiveness self-check) that I can run locally before wiring it into the full endpoint. Clearly note in the output which validation steps I can complete via DJI Pilot 2 import alone, versus which require an actual flight.
