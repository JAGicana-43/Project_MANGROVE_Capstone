"""Orchestrates tiling -> features -> clustering -> result export for a single GeoTIFF."""
from __future__ import annotations

import json
import math
import pickle
from datetime import datetime, timezone
from pathlib import Path

import cv2
import numpy as np

from .cluster_labeling import auto_label_clusters
from .clustering import best_by_silhouette, fit_clusters, fit_pca, fit_scaler, sweep_k
from .features import ResNet18FeatureExtractor, compute_exg, compute_gli, compute_texture_density, compute_vari
from .grid_aggregation import aggregate_to_grid, save_grid_map, save_grid_results
from .io_utils import write_rows
from .suitability_scoring import (
    DEFAULT_SUITABILITY_SCORES,
    save_scored_grid_results,
    save_suitability_map,
    save_web_overlay_png,
    score_grid_cells,
    summarize_scores,
)
from .model_registry import (
    UNLABELED,
    artifact_paths,
    compute_model_id,
    save_model,
    set_active_model,
)
from .spatial_smoothing import smooth_labels
from .tiling import Tile, read_geotiff, tile_raster
from .visualization import build_cluster_map, cluster_color, save_cluster_map

BATCH_SIZE = 32
RANDOM_STATE = 42
FEATURE_SET_NAMES = {
    "full": "resnet18_sentinel2_moco+vari+texture+exg+gli",
    "texture_vari": "vari+texture_only",
}


PROGRESS_FILENAME = "progress.json"


def _write_progress(output_dir: Path | None, processed: int, total: int) -> None:
    """Best-effort, non-fatal: a stray write error here shouldn't take down
    an otherwise-successful classification run. Written atomically (temp
    file + rename) so the reading side (ClassificationRunner, polling this
    file from a separate process) never sees a half-written file -- a
    genuine risk here since this gets written every batch, many times a
    second, while something else reads it on its own schedule."""
    if output_dir is None:
        return
    try:
        tmp_path = output_dir / f".{PROGRESS_FILENAME}.tmp"
        final_path = output_dir / PROGRESS_FILENAME
        tmp_path.write_text(json.dumps({"processed": processed, "total": total}))
        tmp_path.replace(final_path)
    except OSError:
        pass


def build_feature_matrix(
    tiles: list[Tile],
    extractor: ResNet18FeatureExtractor | None,
    feature_set: str = "full",
    progress_dir: Path | None = None,
) -> np.ndarray:
    """feature_set='full' is the normal 516-dim (512 ResNet + VARI + texture
    + ExG + GLI) vector -- ExG and GLI added 2026-09-02 as two more RGB-only
    vegetation signals alongside VARI, aimed at giving the clustering more
    to separate vegetation from bare soil/water on, cheaply (see
    features.py's docstrings for why each one is distinct, not redundant).
    feature_set='texture_vari' skips ResNet entirely and clusters on just
    [VARI, texture] -- a diagnostic to check whether those two signals
    carry a usable dense/sparse split on their own, without being diluted by
    512 ResNet dimensions that dominate distance calculations by sheer count.

    progress_dir, when given, gets a small progress.json written after each
    batch -- this is the dominant-cost step (ResNet inference), so it's the
    one place fine-grained progress actually matters. Written to a file
    rather than relied on via the subprocess's stdout: verified directly
    (real dispatch, polled the DB while it ran) that Symfony Process's
    output callback on this Windows setup does not deliver stdout
    incrementally -- it only arrives in one lump when the process exits,
    making a stdout-based progress signal silently useless. A file that
    the poll loop reads directly sidesteps subprocess pipe buffering
    entirely instead of depending on it."""
    rows = []
    for start in range(0, len(tiles), BATCH_SIZE):
        batch = tiles[start : start + BATCH_SIZE]
        resnet_batch = extractor.extract_batch([t.image for t in batch]) if feature_set == "full" else None
        for i, tile in enumerate(batch):
            vari = compute_vari(tile.image)
            texture = compute_texture_density(tile.image)
            exg = compute_exg(tile.image)
            gli = compute_gli(tile.image)
            if feature_set == "full":
                rows.append(np.concatenate([resnet_batch[i], [vari], [texture], [exg], [gli]]))
            else:
                rows.append(np.array([vari, texture, exg, gli], dtype=np.float64))
        processed = min(start + BATCH_SIZE, len(tiles))
        print(f"  features: {processed}/{len(tiles)} tiles", flush=True)
        _write_progress(progress_dir, processed, len(tiles))
    return np.stack(rows)


def save_results(tiles: list[Tile], labels: np.ndarray, cluster_labels: dict[int, str], output_path: Path) -> None:
    rows = []
    for tile, label in zip(tiles, labels):
        min_x, min_y, max_x, max_y = tile.geo_bounds
        rows.append(
            {
                "tile_id": tile.tile_id,
                "pixel_row": tile.pixel_row,
                "pixel_col": tile.pixel_col,
                "geo_min_x": min_x,
                "geo_min_y": min_y,
                "geo_max_x": max_x,
                "geo_max_y": max_y,
                "nodata_fraction": round(tile.nodata_fraction, 4),
                "cluster": int(label),
                "label": cluster_labels[int(label)],
            }
        )
    write_rows(rows, output_path)


def save_sample_tiles(tiles: list[Tile], labels: np.ndarray, output_dir: Path, samples_per_cluster: int = 5) -> None:
    output_dir.mkdir(parents=True, exist_ok=True)
    # Clear stale samples from a previous run (e.g. a different k) -- otherwise
    # leftover cluster*_tile*.png files from a run that no longer exists would
    # sit alongside the current ones and look like part of this result.
    for stale in output_dir.glob("cluster*_tile*.png"):
        stale.unlink()

    by_cluster: dict[int, list[int]] = {}
    for idx, label in enumerate(labels):
        by_cluster.setdefault(int(label), []).append(idx)

    for label, indices in sorted(by_cluster.items()):
        for idx in indices[:samples_per_cluster]:
            tile = tiles[idx]
            bgr = cv2.cvtColor(tile.image, cv2.COLOR_RGB2BGR)
            cv2.imwrite(str(output_dir / f"cluster{label}_tile{tile.tile_id}.png"), bgr)


FEATURES_CACHE_FILENAME = "features_cache.pkl"


def _features_cache_path(output_dir: Path) -> Path:
    return output_dir / FEATURES_CACHE_FILENAME


def _load_features_cache(
    output_dir: Path, geotiff_path: Path, tile_size: int, max_nodata_fraction: float, feature_set: str
) -> dict | None:
    """The two-pass labeling flow (see ClassificationController's docblock)
    calls run_classification() twice against the *same* output_dir for the
    *same* GeoTIFF -- once with an auto k-sweep, once with a forced k after
    labels are written. Tiling + feature extraction (the ~35s ResNet cost)
    are identical between those two calls; only clustering differs. This
    cache lets the second call skip straight to clustering instead of
    redoing that work from scratch.

    Deliberately scoped to output_dir (one classification run) rather than
    a global cache keyed by file hash -- there's no reuse across different
    runs to worry about, so this only needs to answer "is this the same
    request as whatever produced the cache sitting in this exact folder,"
    not "has this exact GeoTIFF+params combination ever been seen anywhere."
    """
    cache_path = _features_cache_path(output_dir)
    if not cache_path.is_file():
        return None

    try:
        with open(cache_path, "rb") as f:
            cache = pickle.load(f)
    except (pickle.PickleError, EOFError, OSError, AttributeError):
        return None

    stat = geotiff_path.stat()
    if (
        cache.get("geotiff_name") != geotiff_path.name
        or cache.get("geotiff_size") != stat.st_size
        or cache.get("geotiff_mtime") != int(stat.st_mtime)
        or cache.get("tile_size") != tile_size
        or cache.get("max_nodata_fraction") != max_nodata_fraction
        or cache.get("feature_set") != feature_set
    ):
        return None

    return cache


def _save_features_cache(
    output_dir: Path,
    geotiff_path: Path,
    tile_size: int,
    max_nodata_fraction: float,
    feature_set: str,
    tiles: list[Tile],
    info,
    feature_matrix: np.ndarray,
) -> None:
    stat = geotiff_path.stat()
    cache = {
        "geotiff_name": geotiff_path.name,
        "geotiff_size": stat.st_size,
        "geotiff_mtime": int(stat.st_mtime),
        "tile_size": tile_size,
        "max_nodata_fraction": max_nodata_fraction,
        "feature_set": feature_set,
        "tiles": tiles,
        "info": info,
        "feature_matrix": feature_matrix,
    }
    with open(_features_cache_path(output_dir), "wb") as f:
        pickle.dump(cache, f, protocol=pickle.HIGHEST_PROTOCOL)


def run_classification(
    geotiff_path: str | Path,
    output_dir: str | Path,
    tile_size: int = 224,
    k: int | None = None,
    k_sweep_min: int = 4,
    k_sweep_max: int = 10,
    samples_per_cluster: int = 5,
    results_format: str = "csv",
    max_nodata_fraction: float = 0.3,
    reset_labels: bool = False,
    grid_cell_size: int = 1120,
    grid_min_tiles: int = 5,
    smoothing_window: int = 3,
    pca_variance: float | None = 0.90,
    feature_set: str = "full",
) -> dict:
    geotiff_path = Path(geotiff_path)
    output_dir = Path(output_dir)
    output_dir.mkdir(parents=True, exist_ok=True)

    cache = _load_features_cache(output_dir, geotiff_path, tile_size, max_nodata_fraction, feature_set)
    if cache is not None:
        print("Reusing tiles/features cached from an earlier pass in this output_dir ...", flush=True)
        tiles = cache["tiles"]
        info = cache["info"]
        feature_matrix = cache["feature_matrix"]
        grid_cells = math.ceil(info.height / tile_size) * math.ceil(info.width / tile_size)
        dropped = grid_cells - len(tiles)
    else:
        print(f"Reading {geotiff_path} ...", flush=True)
        image, mask, info = read_geotiff(str(geotiff_path))
        tiles = tile_raster(image, mask, info, tile_size=tile_size, max_nodata_fraction=max_nodata_fraction)
        if not tiles:
            raise ValueError("No tiles were produced from the given GeoTIFF (all tiles exceeded the nodata threshold).")

        grid_cells = math.ceil(info.height / tile_size) * math.ceil(info.width / tile_size)
        dropped = grid_cells - len(tiles)
        feature_step_desc = "Running ResNet18 feature extraction" if feature_set == "full" else "Computing VARI+texture features (no ResNet)"
        print(
            f"Tiled into {grid_cells} candidate tiles, dropped {dropped} as >{max_nodata_fraction:.0%} nodata, "
            f"{len(tiles)} remain. {feature_step_desc} ...",
            flush=True,
        )

        extractor = ResNet18FeatureExtractor() if feature_set == "full" else None
        feature_matrix = build_feature_matrix(tiles, extractor, feature_set=feature_set, progress_dir=output_dir)
        _save_features_cache(output_dir, geotiff_path, tile_size, max_nodata_fraction, feature_set, tiles, info, feature_matrix)

    scaler, normalized = fit_scaler(feature_matrix)

    pca, reduced = fit_pca(normalized, pca_variance, random_state=RANDOM_STATE)
    if pca is not None:
        print(
            f"PCA: reduced {normalized.shape[1]} -> {pca.n_components_} dims "
            f"(retaining {pca.explained_variance_ratio_.sum():.1%} variance, target was {pca_variance:.0%})",
            flush=True,
        )
    else:
        print("PCA disabled -- clustering on the full un-reduced feature space", flush=True)

    k_values = list(range(k_sweep_min, k_sweep_max + 1))
    print(f"Sweeping k={k_sweep_min}..{k_sweep_max} to compare silhouette scores ...", flush=True)
    sweep_results = sweep_k(reduced, k_values, random_state=RANDOM_STATE)
    for result in sweep_results:
        print(f"  k={result['k']}: silhouette={result['silhouette']:.4f}", flush=True)

    if k is not None:
        chosen = next((r for r in sweep_results if r["k"] == k), None)
        if chosen is None:
            kmeans, labels, silhouette = fit_clusters(reduced, k=k, random_state=RANDOM_STATE)
            chosen = {"k": k, "kmeans": kmeans, "labels": labels, "silhouette": silhouette}
        selection_method = f"explicit --k override ({k})"
    else:
        chosen = best_by_silhouette(sweep_results)
        selection_method = f"auto-selected by highest silhouette (k={chosen['k']})"

    print(f"Using k={chosen['k']} -- {selection_method}", flush=True)
    kmeans, labels, silhouette = chosen["kmeans"], chosen["labels"], chosen["silhouette"]
    chosen_k = chosen["k"]

    print("Clustering done. Saving outputs ...", flush=True)

    smoothed_labels = smooth_labels(tiles, labels, tile_size=tile_size, window=smoothing_window)
    tiles_changed_by_smoothing = int(np.sum(smoothed_labels != labels))
    if smoothing_window > 1:
        print(
            f"Spatial smoothing (window={smoothing_window}x{smoothing_window} tiles): "
            f"{tiles_changed_by_smoothing}/{len(tiles)} tiles reassigned to their locally dominant cluster",
            flush=True,
        )
    else:
        print("Spatial smoothing disabled (window<=1)", flush=True)
    # Raw `labels` (and `silhouette`, computed above) stay the fit's true
    # output for model comparison/versioning. Everything below this point is
    # the *display* layer, so it uses the smoothed assignments instead --
    # smoothing never touches the saved KMeans/scaler artifact itself.
    labels = smoothed_labels

    model_id = compute_model_id(
        geotiff_path=geotiff_path,
        tile_size=tile_size,
        k=chosen_k,
        max_nodata_fraction=max_nodata_fraction,
        feature_dim=feature_matrix.shape[1],
        random_state=RANDOM_STATE,
        feature_extractor_name=FEATURE_SET_NAMES[feature_set],
        pca_variance=pca_variance,
    )
    cluster_ids = sorted(int(c) for c in np.unique(labels))
    artifact = artifact_paths(output_dir, model_id)
    save_model(artifact, scaler, kmeans, cluster_ids, pca=pca)

    # Automatic: each cluster's label comes from majority-voting its own
    # tiles' pixel content (see cluster_labeling.py), not a human review
    # step -- there is no "unlabeled, waiting for review" state anymore,
    # every run resolves real labels and is immediately scoreable in this
    # same call. Still written to the same labels.json shape a human-edited
    # file would use, so the artifact folder stays inspectable/debuggable.
    print("Auto-labeling clusters from tile pixel content ...", flush=True)
    cluster_labels = auto_label_clusters(tiles, labels, cluster_ids)
    for cluster_id in cluster_ids:
        print(f"  cluster {cluster_id}: {cluster_labels[cluster_id]}", flush=True)
    artifact.labels_path.write_text(json.dumps({
        "model_id": artifact.model_id,
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "instructions": "Auto-labeled from tile pixel content -- see cluster_labeling.py. Not hand-edited.",
        "labels": {str(cid): cluster_labels[cid] for cid in cluster_ids},
    }, indent=2))
    unlabeled_clusters: list[int] = []

    # Every run is now a single, complete pass -- there's no separate
    # "sweep" vs. "committed" distinction left to track (that was the old
    # two-pass labeling flow's concept), so this is always the active model.
    is_active = True
    active_model_path = set_active_model(output_dir, artifact, chosen_k)

    results_path = output_dir / f"results.{results_format}"
    save_results(tiles, labels, cluster_labels, results_path)

    sample_tiles_dir = output_dir / "sample_tiles"
    print("Saving sample tile PNGs ...", flush=True)
    save_sample_tiles(tiles, labels, sample_tiles_dir, samples_per_cluster=samples_per_cluster)

    print("Building cluster map ...", flush=True)
    cluster_map_path = output_dir / "cluster_map.png"
    canvas = build_cluster_map(tiles, labels, info.height, info.width)
    clusters, counts = np.unique(labels, return_counts=True)
    counts_by_cluster = {int(c): int(n) for c, n in zip(clusters, counts)}
    legend_rows = [
        {
            "cluster_id": cid,
            "color": cluster_color(cid),
            "label": cluster_labels[cid] if cluster_labels[cid] != UNLABELED else f"cluster {cid}",
            "count": counts_by_cluster[cid],
            "is_unlabeled": cluster_labels[cid] == UNLABELED,
        }
        for cid in cluster_ids
    ]
    save_cluster_map(canvas, cluster_map_path, legend_rows=legend_rows)

    print(f"Aggregating to a {grid_cell_size}px sampling grid (min {grid_min_tiles} tiles/cell) ...", flush=True)
    sampling_cells = aggregate_to_grid(tiles, labels, cluster_labels, info, cell_size=grid_cell_size, min_valid_tiles=grid_min_tiles)
    grid_results_path = output_dir / f"grid_results.{results_format}"
    save_grid_results(sampling_cells, grid_results_path)
    grid_map_path = output_dir / "grid_map.png"
    save_grid_map(sampling_cells, info.height, info.width, grid_map_path)
    insufficient_cell_count = sum(1 for c in sampling_cells if c.majority_label == "INSUFFICIENT_DATA")

    print("Scoring suitability ...", flush=True)
    scored_cells = score_grid_cells(sampling_cells, DEFAULT_SUITABILITY_SCORES)
    suitability_results_path = output_dir / f"suitability_results.{results_format}"
    save_scored_grid_results(scored_cells, suitability_results_path)
    suitability_map_path = output_dir / "suitability_map.png"
    save_suitability_map(scored_cells, info.height, info.width, suitability_map_path)
    web_overlay_path = output_dir / "suitability_overlay_web.png"
    save_web_overlay_png(scored_cells, info.height, info.width, web_overlay_path)
    score_summary = summarize_scores(scored_cells)

    # Every real dispatch today is single-pass (auto-labeling retired the
    # two-pass k-sweep -> label -> forced-k flow this comment used to
    # describe) -- nothing will call run_classification() against this
    # output_dir again after this, whether k was explicit or auto-selected,
    # so the cached tiles/features (a non-trivial amount of disk, since they
    # include every tile's raw image array) are always dead weight from
    # here on. Confirmed via the real dispatch sites: ClassificationController's
    # only frontend-reachable call never passes k.
    cache_path = _features_cache_path(output_dir)
    if cache_path.is_file():
        cache_path.unlink()

    return {
        "crs": info.crs,
        "candidate_tile_count": grid_cells,
        "tile_count": len(tiles),
        "tiles_dropped_nodata": dropped,
        "k_sweep": [{"k": r["k"], "silhouette": r["silhouette"]} for r in sweep_results],
        "chosen_k": chosen_k,
        "k_selection_method": selection_method,
        "smoothing_window": smoothing_window,
        "tiles_changed_by_smoothing": tiles_changed_by_smoothing,
        "feature_set": feature_set,
        "pca_variance_target": pca_variance,
        "pca_components": int(pca.n_components_) if pca is not None else None,
        "pca_variance_retained": float(pca.explained_variance_ratio_.sum()) if pca is not None else None,
        "feature_dim_original": int(normalized.shape[1]),
        "tiles_per_cluster": counts_by_cluster,
        "cluster_colors": {cid: cluster_color(cid) for cid in cluster_ids},
        "cluster_labels": cluster_labels,
        "unlabeled_clusters": unlabeled_clusters,
        "is_active": is_active,
        "active_model_path": str(active_model_path) if active_model_path else None,
        "silhouette_score": silhouette,
        "results_path": str(results_path),
        "sample_tiles_dir": str(sample_tiles_dir),
        "cluster_map_path": str(cluster_map_path),
        "model_id": model_id,
        "model_path": str(artifact.model_path),
        "labels_path": str(artifact.labels_path),
        "grid_cell_size": grid_cell_size,
        "grid_min_tiles": grid_min_tiles,
        "grid_cell_count": len(sampling_cells),
        "grid_insufficient_cell_count": insufficient_cell_count,
        "grid_results_path": str(grid_results_path),
        "grid_map_path": str(grid_map_path),
        "suitability_scores_config": DEFAULT_SUITABILITY_SCORES,
        "suitability_results_path": str(suitability_results_path),
        "suitability_map_path": str(suitability_map_path),
        "web_overlay_path": str(web_overlay_path),
        "score_summary": score_summary,
    }
