# Classification Pipeline — Status

Branch: `feature/classification-pipeline`
Last updated: 2026-09-11

This document tracks the tile-classification pipeline living in
`classification_service/` and how it's wired into the Laravel app. It
previously described a CLI-only prototype not yet connected to anything —
that's no longer accurate. **It is now fully integrated and working** inside
the Project M.A.N.G.R.O.V.E workspace (Orthomosaic section).

**UPDATE (2026-09-11): the two-pass flow described below (auto-sweep ->
human labels a dropdown per cluster -> forced-k second pass) has since been
replaced by a single-pass, fully-automatic flow.** Clusters now auto-label
themselves from each tile's own pixel content (`cluster_labeling.py`) —
there is no human-labeling round trip left to wait on. `RunClassificationJob`
clusters, labels, scores, and generates sampling points in one dispatch.
The "End-to-end flow" and "What's still pending" sections below have been
updated to match; the "Fixed bugs" and design-rationale sections are left
as-is since they're an accurate historical record of real bugs found and
fixed in the (now-retired) two-pass flow, not a description of current
behavior.

## Finalized design (2026-09-02)

The core question this pipeline answers: **given an uploaded orthomosaic,
which areas are suitable for mangrove rehabilitation planting, and which
aren't?** The answer is a 3-tier suitability score per grid cell, not a
strict binary — see "Suitability scoring" below for why.

### End-to-end flow, as implemented (single-pass, since the UPDATE above)

1. **Upload** — a researcher uploads a GeoTIFF orthomosaic via the workspace.
   Parsed and previewed client-side (browser); the server stores the actual
   file once upload completes.
2. **Analyze (one pass, fully automatic)** —
   `ClassificationController::runClassification()` dispatches
   `RunClassificationJob` onto a dedicated `classification` queue. This runs
   the Python pipeline with an auto k-sweep, clusters, then immediately
   **auto-labels every cluster from its own tiles' pixel content**
   (`cluster_labeling.py` — HSV-based heuristics, no human review step),
   scores the grid, and generates sampling points, all in this one dispatch.
   Real-time progress percentage and hard cancel (kills the actual OS
   subprocess) are both supported.
3. **View result** — the overlay renders on the map as a semi-transparent
   (65% opacity) image layer, so the real orthomosaic imagery stays visible
   underneath the suitability colors for visual cross-checking. A legend
   (land-cover names/colors, not suitability-tier names) renders alongside
   it. A researcher can still correct individual mislabeled cells by hand
   afterward — see "Per-cell manual correction" below — that's the one
   human-in-the-loop step left, scoped to fixing specific mistakes rather
   than naming whole clusters.
4. **Re-analyze** — starting a fresh analysis on the same orthomosaic first
   deletes every prior run for it (DB rows cascade via FK constraints; the
   output directory is removed from disk) — a re-analysis supersedes the
   old result rather than accumulating disk usage indefinitely.

The old `Orthomosaic.last_cluster_labels`/`last_cluster_labels_model_id`
pre-fill mechanism described in "Fixed bugs" below was part of the retired
human-labeling step and is no longer read by the current flow.

### Suitability scoring (finalized, not provisional)

```
bare_soil   -> 1.0   (suitable: open ground, no competition)
vegetation  -> 0.5   (marginal: already vegetated, still a possible site)
open_water  -> excluded (not suitable: can't plant underwater)
```

Deliberately a 3-tier score, not a strict binary — an already-vegetated area
is a genuinely different case from a submerged one (it could still be a
viable, if less ideal, site), so collapsing to strict suitable/not-suitable
would lose information a researcher needs when picking where to sample.

`vegetation` is a single merged class, not split into dense/sparse: 5
independent approaches were tried (cluster relabeling, spatial smoothing,
PCA, PCA+texture, texture+VARI alone) and none produced a spatially coherent
density split. Two of those (`pca_cluster_overlay.png`,
`texture_vari_overlay.png`, still on disk in
`classification_output_real/`) are historical artifacts of that abandoned
experiment, generated against a different test file
(`orthophotomosaic_La_Chunga.tif`, Colombia) using raw unsupervised cluster
coloring (not suitability scoring) — not a more-accurate alternative result,
just a different, earlier exploration that didn't pan out.

### Feature extractor swap: ImageNet -> Sentinel-2 pretrained (2026-09-02)

The deep-embedding component (512-dim, feeding into PCA + K-Means alongside
VARI/texture/ExG/GLI) was ResNet18 pretrained on ImageNet — general photo
classification (cats, cars, household objects), never trained to
distinguish land-cover types. It happened to separate water/vegetation/soil
only incidentally, because those are visually distinct enough for a
generic classifier to notice by accident, not because it learned to care
about that distinction.

Swapped to `torchgeo`'s ResNet18 pretrained on Sentinel-2 RGB imagery
(SSL4EO-S12 dataset, self-supervised MoCo) — same architecture, same
512-dim output, so nothing downstream (PCA, clustering, caching, the
water-detection override) needed to change. Verified empirically on the
real test orthomosaic, not assumed:
- Silhouette score (cluster separation quality) went from ~0.14 to ~0.36 —
  roughly 2.5x — a much bigger jump than the earlier ResNet-vs-DINOv2 test
  ever showed (that one didn't beat ResNet18 at all, which is why ResNet18
  was kept originally).
- Visually confirmed the resulting clusters are semantically coherent, not
  just statistically separated: multiple distinct mud/soil texture
  sub-types, two water sub-variants (both genuinely water), one clean
  vegetation cluster — versus the old extractor's blunter groupings.
- Ran a full real two-pass classification through the actual Laravel
  pipeline (not just the standalone comparison script) to confirm nothing
  downstream broke.

Reproducible comparison: `scripts/compare_sentinel2_resnet.py` (mirrors the
existing `compare_feature_extractors.py` ResNet-vs-DINOv2 pattern).

**Domain-mismatch note, handled deliberately**: the Sentinel-2 weights'
own published preprocessing expects raw satellite reflectance values
(mean=0, std=10000) for 10m-resolution atmospherically-corrected imagery —
not an 8-bit drone photo. `features.py` uses the same ImageNet-style 0-1 +
mean/std normalization the old extractor used instead, which empirically
transfers better to this data than the weights' own native normalization
would (tested both; ImageNet-style normalization is what produced the
0.36 silhouette result above). New dependency: `torchgeo`, added to
`requirements.txt`.

### Additional RGB-only vegetation indices: ExG, GLI (2026-09-02)

Added `compute_exg`/`compute_gli` in `features.py` alongside the existing
VARI, giving the clustering two more independent, cheap vegetation signals
(one absolute/brightness-sensitive, one ratio-normalized like VARI) without
needing a NIR band. Feature vector is now 516-dim (512 ResNet + VARI +
texture + ExG + GLI), up from 514.

### Supplementary water detection (finalized 2026-09-02)

Tile-based clustering has a structural blind spot: a 224x224 tile with a
narrow stream or small pond that doesn't dominate the crop gets absorbed
into whatever land cover surrounds it (usually vegetation) — confirmed
directly that raising k from 4 to 7 does not fix this, since the tile's
feature vector still reads as "vegetation with something else," not
"water," regardless of cluster count.

`classification/water_detection.py` adds a lightweight RGB-only heuristic
(no NIR band available, so NDWI isn't usable) applied per-tile during grid
aggregation, independent of the K-Means clustering: a tile is flagged
water-like if it shows meaningful sun-glint (bright/overexposed pixel
fraction ≥ 4%) *and* an overall color that isn't too saturated to be water
(≤150 on a 0-255 HSV scale — this second check is what correctly rules out
a vegetation tile with bright sky visible through canopy gaps). Calibrated
against 20 known-labeled sample tiles from a real run (100% correct, real
margin on both thresholds) — see `scripts/calibrate_water_heuristic.py`.
A cell is overridden to `open_water` if at least half its contributing
tiles are flagged this way, regardless of what the cluster vote says.

Verified on the real test orthomosaic: `open_water` cells went from 44 to
66 after this was added — the waterway that previously had a visible gap
(showing as vegetation mid-channel) is now continuous, and several small
isolated ponds near the AOI edges are now correctly excluded that were
previously scored as plantable vegetation.

**Revised same day** after direct user feedback ("some areas are labeled
as water even if there's vegetation in there"): the first version flagged
a tile as water based on an absolute glint-fraction floor alone, which
misfired on tiles that were mostly vegetation with only a small puddle.
Fixed by comparing water's glint fraction against the tile's *vegetation*
fraction (a green-hue/saturation mask) and only overriding when water
genuinely exceeds vegetation, not merely clears a floor —
`land_cover_fractions()` in the same module. Re-verified against the same
20 calibration tiles: 18/20 correct (up from the complaint-causing
version), with both remaining misses being genuinely ambiguous boundary
cases (dry cracked mud with sun sheen; a water tile roughly half-covered
in algae/moss) rather than clear-cut errors. Visually confirmed on the
real orthomosaic: scattered false-positive water speckles far from the
actual waterway are gone; water now stays concentrated around the real
channel.

**Known remaining limitation**: distinguishing "puddle/tidal standing
water on a mudflat" (potentially still suitable rehabilitation ground,
since mangroves grow in the intertidal zone that's underwater part of the
tidal cycle) from "permanent open water" (never suitable) is not solved by
a single still image — a puddle and a permanent pond can look
pixel-identical depending on when the drone flew. Resolving this properly
would need multi-temporal imagery of the same AOI at different tide
levels. The multi-timeline orthomosaic comparison feature (Compare Surveys
— `SurveyComparisonService`) was built for exactly this class of problem
and is live (see "What's still pending" below), but it compares two
already-classified surveys against each other; it doesn't retroactively
fix a single survey's puddle-vs-permanent-water ambiguity on its own.

### Grid resolution (finalized default: 224px)

The original CLI default of 1120px (5x5 tiles) was tuned against a smaller
test file — on a real drone orthomosaic (multiple thousand px per side) it
produced only ~30 total grid cells covering the whole image, losing almost
all spatial detail and not tracking real vegetation/soil/water boundaries at
all (verified directly on a 7389x5111px orthomosaic). Default is now 224px
(one tile per cell, no aggregation majority-vote), configurable via
`config/classification.php` (`grid_cell_size`/`grid_min_tiles`) —
**changing this requires restarting the queue worker**, since a long-running
`queue:work` process caches config in memory.

## Fixed bugs (worth knowing about if something looks newly broken)

- **Stale cluster-label pre-fill after a pipeline change**: the labeling
  form's pre-fill (`Orthomosaic.last_cluster_labels`) matched purely by
  cluster_id index with no check that the underlying fit was still
  comparable. Confirmed directly this broke real results: swapping the
  feature extractor changed what every cluster_id actually represents, but
  the pre-fill silently carried the *old* index-to-label mapping onto the
  *new* clustering anyway, producing confidently-wrong defaults that looked
  plausible enough to submit without noticing (bare_soil/vegetation/
  open_water ended up essentially shuffled). Fixed by storing the
  `model_id` the labels were actually submitted against
  (`Orthomosaic.last_cluster_labels_model_id`) and only pre-filling when a
  later run's own model_id matches it — otherwise the form now starts
  blank rather than confidently wrong. Any pipeline change that alters
  `compute_model_id()`'s inputs (tile size, nodata threshold, feature set,
  extractor, k, pca variance) now correctly invalidates old pre-fills
  instead of silently misapplying them.
- **`FEATURE_SET_NAMES` string went stale**: still read
  `"resnet18_imagenet+vari+texture"` after the extractor was swapped to
  Sentinel-2 and ExG/GLI were added — cosmetically wrong, and meant
  `model_id` wasn't fully capturing the actual extractor identity (though
  `feature_dim` changing from 514->516 did still force a new model_id on
  its own). Corrected to `"resnet18_sentinel2_moco+vari+texture+exg+gli"`.

- **Label file JSON format**: `submitLabels()` was writing the cluster-label
  file as a JSON array instead of an object whenever cluster ids were
  sequential from 0 (always the case) — PHP can't distinguish
  `{"0":"a","1":"b"}` from `["a","b"]` without `JSON_FORCE_OBJECT`. This
  crashed every real second-pass submission with a Python
  `AttributeError: 'list' object has no attribute 'get'` until fixed. If you
  see that exact error again, check this first.
- **Queue worker staleness**: a long-lived `queue:work` process caches PHP
  code and config in memory — editing `ClassificationRunner`,
  `RunClassificationJob`, or `config/classification.php` requires restarting
  the worker for changes to take effect. A stale worker running old code was
  the root cause of a multi-hour "why doesn't this work" investigation.
- **No process supervision**: the worker has no supervisor (systemd/pm2/
  Windows-service equivalent) keeping it alive — if it dies, jobs just sit
  queued forever with no error shown anywhere. A setup script
  (`setup-queue-worker-service.ps1`) registers it as a self-restarting
  Windows Scheduled Task; run it once per machine.

### Per-cell manual correction (finalized 2026-09-02)

K-means is unsupervised -- it can't be "retrained" on labels the way a
supervised classifier can, and cluster-labeling (whether the retired
human-driven two-pass version or the current pixel-content auto-labeling)
names whole clusters at once (potentially hundreds of tiles per label). To fix
specific mistakes without redoing a whole cluster's label, a researcher can
now click an individual grid cell on the finished map and override just
that cell's label directly.

- `GET /classification-runs/{run}/grid-cells` returns every cell's id,
  label, score, geo bounds, and whether it's been manually overridden.
- `PATCH /classification-runs/{run}/grid-cells/{cell}` validates the new
  label, recomputes `suitability_score` using the same 3-tier rule as the
  Python pipeline (kept in sync manually -- `ClassificationController::SUITABILITY_SCORES`
  mirrors `suitability_scoring.py`'s `DEFAULT_SUITABILITY_SCORES`), and
  marks the cell `manually_overridden = true`.
- Workspace UI: a "Correct Grid Cells" toggle renders every cell as an
  invisible clickable rectangle (using its real geo bounds, not a raster
  redraw) while active; clicking one opens a small label picker. A
  manually-corrected cell gets a visible colored patch that stays on the
  map even after edit mode is turned off, so the correction is never
  invisible.
- This is intentionally a human-override layer, not a retraining
  mechanism -- see "Manual labels vs. training a classifier" below for why
  actual supervised training isn't being done yet.
- Verified end-to-end on a real completed run: fetched 564 cells, updated
  one from `vegetation` to `open_water`, confirmed score recalculated to
  excluded (null) and `manually_overridden` set. Authorization (non-member
  blocked, 403) and cross-run cell mismatch (blocked, 404) both verified
  directly.

### Manual labels vs. training a classifier

Discussed directly with the user: manually correcting tiles cannot
"retrain K-means" (it's unsupervised -- fits by feature similarity, not by
label). The technique that *would* use manual labels the way they meant is
training an actual supervised classifier (e.g. k-NN or a small classifier)
on the same embeddings, replacing "K-means then label" with a directly
trained model. Deferred for now: there is currently only one real test
orthomosaic in this environment (the Davao test file and
`orthophotomosaic_La_Chunga.tif` are confirmed identical), so a classifier
trained today would risk overfitting to that one image's specific
lighting/color rather than generalizing -- worth revisiting once more than
one real site's imagery is available.

## What's still pending / out of scope for now

**UPDATE (2026-09-11): the three items below marked done are no longer
pending** — left here struck through for history rather than deleted, since
they were genuinely accurate when first written.

- ~~Sampling point generation — hidden behind a manual button.~~ **Done.**
  Automatic now: `RunClassificationJob` calls `SamplingPointGenerator`
  itself at the end of every successful run (bare_soil-only, fixed 15m
  spacing), no manual button anymore.
- ~~Technician assignment / soil-reading ingestion / mobile handoff — not
  started.~~ **Done.** Sanctum-based mobile API (`Api\MobileAuthController`,
  `Api\MobileSyncController`), web-side assignment UI
  (`SamplingPointAssignmentController`), `SoilReadingService`, and the
  species-aware Soil Readings map/table page are all built and working —
  mobile sync is the only writer of `soil_readings` now.
- ~~Multi-timeline comparison — deferred, schema unused.~~ **Done.**
  `SurveyComparisonService` matches `SamplingGrid` cells between two
  classification runs by real-world nearest-centroid (not grid index, since
  independent flights don't share a tiling origin), reports both land-cover
  transitions and suitability-score change. Live in the workspace as
  "Compare Surveys," unlocked once a project has 2+ classified
  orthomosaics.
- **Generalization beyond the current test orthomosaics** — still open.
  Clustering is fitted fresh per orthomosaic (no cross-orthomosaic model
  reuse), so this is inherently self-adjusting per site rather than a
  generalization concern in the way a single fixed model would be, but only
  one real test orthomosaic has ever been available to verify that
  assumption against.

## Known limitations (not bugs, inherent to the approach)

- Silhouette scores from K-Means on generic ImageNet ResNet18 features are
  low in absolute terms — it reliably separates spectrally distinct classes
  (water vs. bare soil vs. vegetation) but not subtler within-vegetation
  differences.
- Labels are unsupervised-cluster-derived + manually named via visual
  inspection each time, not from ground-truth/field-verified data.
- The soft/hard-cancel race documented in earlier planning is resolved for
  hard cancel (verified: actually kills the OS subprocess); a narrow
  completion-write race from the earlier soft-cancel design is no longer
  applicable since hard cancel replaced it.
