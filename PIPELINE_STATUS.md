# Classification Pipeline — Status

Branch: `feature/classification-pipeline`
Last updated: 2026-08-22

This is a handoff doc for the standalone tile-classification prototype living in
`classification_service/`. It is **not** wired into the Flask app
(`mission_generation_service/`), the Laravel app, or any route/UI — it's a
command-line script only, by design, until the pieces below are deliberately
connected.

## What's built and validated

- **GeoTIFF ingestion** (`classification/tiling.py`): reads CRS + affine
  transform with rasterio, rescales arbitrary dtypes to uint8, tiles into
  224x224 patches with OpenCV, keeps pixel coords + geographic bounds per tile.
- **Nodata handling**: reads the file's alpha/mask band via
  `dataset_mask()` and drops any tile whose nodata pixel share exceeds
  `--max-nodata-fraction` (default 0.3). Verified this correctly excludes the
  transparent padding around the orthomosaic's irregular, rotated flight
  footprint — confirmed via a reconstructed cluster map showing no gaps
  inside the real footprint.
- **Feature extraction** (`classification/features.py`): pretrained ResNet18
  (ImageNet weights, classification head removed, CPU-only) → 512-dim
  embedding per tile, batched (32 tiles/batch) for throughput. Plus VARI
  (Visible Atmospherically Resistant Index) from RGB → 513-dim feature vector.
- **Clustering with a k-sweep, not a fixed k** (`classification/clustering.py`):
  fits K-Means across a configurable k range (default 4–10), reports
  silhouette score per k, and either auto-picks the best-scoring k or uses an
  explicit `--k` override.
- **Model + label versioning** (`classification/model_registry.py`): each fit
  is fingerprinted into a `model_id` (input file identity + tile size + k +
  nodata threshold + feature dims + extractor name). The fitted
  StandardScaler + KMeans are saved via joblib, paired with a human-readable
  cluster label file sharing the same filename stem. A label file is only
  ever reused if it was written for the *exact* same fit — a different k or
  input silently gets a fresh `UNLABELED` template instead of misapplying
  stale names. One artifact per output dir can be marked **ACTIVE** (see
  below) so multiple explored k's don't create ambiguity about which is real.
- **Grid aggregation** (`classification/grid_aggregation.py`): buckets
  tile-level labels into a coarser sampling grid (configurable cell size,
  default 1120px = 5x5 tiles), assigns each cell the majority label among its
  tiles, and flags cells below a minimum tile count (default 5) as
  `INSUFFICIENT_DATA` rather than guessing.
- **Outputs per run**: `results.csv`/`.json` (tile-level, with cluster +
  label), `grid_results.csv`/`.json` (cell-level, with majority label +
  full label breakdown), `cluster_map.png` and `grid_map.png` (flat-color
  reconstructions with a legend), `sample_tiles/` (sample PNGs per cluster
  for visual QA).

## Active model — this is the one to use

Location: `classification_service/classification_output_real/model_artifacts/`

| | |
|---|---|
| Active pointer | `ACTIVE_MODEL.json` |
| Model ID | `8e034202c257` |
| k | **5** (deliberately chosen over the silhouette-optimal k=4, because k=4 merges open water and mudflat into one cluster, which isn't acceptable for suitability scoring — see decision note below) |
| Model artifact | `model_8e034202c257.joblib` (fitted StandardScaler + KMeans) |
| Label file | `model_8e034202c257.labels.json` |

Finalized labels (5 clusters → 4 distinct classes; two clusters both map to
`sparse_mangrove_fringe`, which is expected — K-Means splits by visual
similarity, not by the taxonomy we care about, so merging sub-clusters under
one label is the normal way to reconcile the two):

```json
{
  "0": "dense_vegetation",
  "1": "open_water",
  "2": "sparse_mangrove_fringe",
  "3": "sparse_mangrove_fringe",
  "4": "bare_soil"
}
```

**Decision note (why k=5, not k=4):** the k-sweep (k=4–10) found k=4 gives the
best silhouette score (0.1233 vs. 0.1031 at k=5), but at k=4, open water and
mudflat collapse into a single cluster. Since suitability scoring needs to
distinguish "unsuitable, underwater" from "candidate site, mudflat," k=5 was
picked deliberately despite the lower silhouette score. Canopy still
fractures into multiple sub-clusters at k=5 (clusters 0/2/3 area) — that's
considered acceptable and handled by mapping duplicate cluster IDs to the
same label, rather than chasing a cleaner but less useful k.

There is also an earlier, non-active model artifact
(`model_ae148a81d657.*`, k=4) still on disk in the same folder from the
sweep exploration — it is **not** the one to use; `ACTIVE_MODEL.json` is the
source of truth for which model_id is current.

## Exact commands to reproduce

All commands run from `classification_service/`, using the project's own
venv (Python 3.13 — system Python 3.14 has no wheels yet for
rasterio/torch/opencv). A system-wide `PROJ_LIB` env var on this machine
points at an incompatible PostGIS proj database, so it must be overridden to
rasterio's bundled one for every run:

```powershell
cd E:\Project_Mangrove_System\classification_service
.venv-classify\Scripts\Activate.ps1
$env:PROJ_LIB  = "$PWD\.venv-classify\Lib\site-packages\rasterio\proj_data"
$env:PROJ_DATA = "$PWD\.venv-classify\Lib\site-packages\rasterio\proj_data"
```

**Re-run classification with the active k=5 model** (reuses the finalized
label file automatically, re-marks it active, does NOT reset labels):

```powershell
python scripts\test_classification.py "D:\Downloads\orthophotomosaic_La_Chunga.tif" `
  --output-dir classification_output_real --k 5
```

**Explore a different k / different input** (does not touch ACTIVE_MODEL.json
unless you also pass `--k`; auto-pick-by-silhouette runs are exploratory by
design):

```powershell
python scripts\test_classification.py <path-to-orthomosaic.tif> --output-dir <dir> --k-min 3 --k-max 10
```

**Start a fresh label file for a given fit** (discards existing labels —
only do this deliberately):

```powershell
python scripts\test_classification.py <path> --output-dir <dir> --k 5 --reset-labels
```

**Grid aggregation** runs automatically as part of every classification call
above (not a separate script). Tune it with:

```powershell
python scripts\test_classification.py <path> --output-dir <dir> --k 5 `
  --grid-cell-size 1120 --grid-min-tiles 5
```

Full flag reference: `python scripts\test_classification.py --help`

## What's still pending

Everything below is **not started**:

- **Suitability scoring rules** — turning `majority_label` per grid cell
  into an actual suitability score/decision for site selection. No scoring
  logic exists yet; `grid_results.csv` currently only carries the raw
  majority label + label breakdown per cell.
- **Sampling point generation** — deriving actual survey/sampling
  coordinates from the grid (e.g. cell centroids, filtered by suitability
  score). `centroid_x`/`centroid_y` are already in `grid_results.csv` per
  cell, but nothing consumes them yet.
- **Flask endpoint integration** — this is a CLI script only
  (`scripts/test_classification.py`). No route in `app.py` (or anywhere
  else) calls into `classification_service/` yet, matching the original
  scope of this work ("prototype and validate," not integrate).
- **Laravel wiring** — no PHP-side code references `classification_service/`
  at all. The Laravel app and this classification service are currently
  fully independent.
- **Generalization beyond this one orthomosaic** — the active model was
  fitted and labeled specifically on `orthophotomosaic_La_Chunga.tif`
  (Chocó, Colombia — Neotropical mangrove). It has not been tested against
  a Philippine mangrove site, which likely has different species
  composition/canopy texture (see prior discussion in this branch's history).

## Known limitations (not bugs, inherent to the current approach)

- Silhouette score at the active k=5 (0.1031) is low in absolute terms —
  K-Means on generic ImageNet ResNet18 features reliably separates
  spectrally distinct classes (water vs. mudflat vs. vegetation) but cannot
  cleanly separate subtler within-vegetation differences. This is why
  canopy still fractures into multiple clusters that get merged under one
  label rather than trusting the raw cluster split.
- Labels are unsupervised-cluster-derived + manually named by visual
  inspection, not from ground-truth/field-verified data.
