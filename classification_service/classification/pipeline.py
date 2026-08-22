"""Orchestrates tiling -> features -> clustering -> result export for a single GeoTIFF."""
from __future__ import annotations

import csv
import json
import math
from pathlib import Path

import cv2
import numpy as np

from .clustering import best_by_silhouette, fit_clusters, fit_scaler, sweep_k
from .features import ResNet18FeatureExtractor, compute_vari
from .grid_aggregation import aggregate_to_grid, save_grid_map, save_grid_results
from .model_registry import (
    UNLABELED,
    artifact_paths,
    compute_model_id,
    load_or_init_labels,
    save_model,
    set_active_model,
)
from .tiling import Tile, read_geotiff, tile_raster
from .visualization import build_cluster_map, cluster_color, save_cluster_map

BATCH_SIZE = 32
RANDOM_STATE = 42
FEATURE_EXTRACTOR_NAME = "resnet18_imagenet+vari"


def build_feature_matrix(tiles: list[Tile], extractor: ResNet18FeatureExtractor) -> np.ndarray:
    rows = []
    for start in range(0, len(tiles), BATCH_SIZE):
        batch = tiles[start : start + BATCH_SIZE]
        resnet_batch = extractor.extract_batch([t.image for t in batch])
        for tile, resnet_features in zip(batch, resnet_batch):
            vari = compute_vari(tile.image)
            rows.append(np.concatenate([resnet_features, [vari]]))
        print(f"  features: {min(start + BATCH_SIZE, len(tiles))}/{len(tiles)} tiles", flush=True)
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

    if output_path.suffix.lower() == ".json":
        output_path.write_text(json.dumps(rows, indent=2))
    else:
        with output_path.open("w", newline="") as f:
            writer = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
            writer.writeheader()
            writer.writerows(rows)


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
) -> dict:
    geotiff_path = Path(geotiff_path)
    print(f"Reading {geotiff_path} ...", flush=True)
    image, mask, info = read_geotiff(str(geotiff_path))
    tiles = tile_raster(image, mask, info, tile_size=tile_size, max_nodata_fraction=max_nodata_fraction)
    if not tiles:
        raise ValueError("No tiles were produced from the given GeoTIFF (all tiles exceeded the nodata threshold).")

    grid_cells = math.ceil(info.height / tile_size) * math.ceil(info.width / tile_size)
    dropped = grid_cells - len(tiles)
    print(
        f"Tiled into {grid_cells} candidate tiles, dropped {dropped} as >{max_nodata_fraction:.0%} nodata, "
        f"{len(tiles)} remain. Running ResNet18 feature extraction ...",
        flush=True,
    )

    extractor = ResNet18FeatureExtractor()
    feature_matrix = build_feature_matrix(tiles, extractor)

    scaler, normalized = fit_scaler(feature_matrix)

    k_values = list(range(k_sweep_min, k_sweep_max + 1))
    print(f"Sweeping k={k_sweep_min}..{k_sweep_max} to compare silhouette scores ...", flush=True)
    sweep_results = sweep_k(normalized, k_values, random_state=RANDOM_STATE)
    for result in sweep_results:
        print(f"  k={result['k']}: silhouette={result['silhouette']:.4f}", flush=True)

    if k is not None:
        chosen = next((r for r in sweep_results if r["k"] == k), None)
        if chosen is None:
            kmeans, labels, silhouette = fit_clusters(normalized, k=k, random_state=RANDOM_STATE)
            chosen = {"k": k, "kmeans": kmeans, "labels": labels, "silhouette": silhouette}
        selection_method = f"explicit --k override ({k})"
    else:
        chosen = best_by_silhouette(sweep_results)
        selection_method = f"auto-selected by highest silhouette (k={chosen['k']})"

    print(f"Using k={chosen['k']} -- {selection_method}", flush=True)
    kmeans, labels, silhouette = chosen["kmeans"], chosen["labels"], chosen["silhouette"]
    chosen_k = chosen["k"]

    print("Clustering done. Saving outputs ...", flush=True)

    output_dir = Path(output_dir)
    output_dir.mkdir(parents=True, exist_ok=True)

    model_id = compute_model_id(
        geotiff_path=geotiff_path,
        tile_size=tile_size,
        k=chosen_k,
        max_nodata_fraction=max_nodata_fraction,
        feature_dim=feature_matrix.shape[1],
        random_state=RANDOM_STATE,
        feature_extractor_name=FEATURE_EXTRACTOR_NAME,
    )
    cluster_ids = sorted(int(c) for c in np.unique(labels))
    artifact = artifact_paths(output_dir, model_id)
    save_model(artifact, scaler, kmeans, cluster_ids)
    if reset_labels:
        print(f"--reset-labels: discarding any prior labels, writing a fresh UNLABELED template for k={chosen_k}", flush=True)
    cluster_labels = load_or_init_labels(artifact, cluster_ids, reset=reset_labels)
    unlabeled_clusters = [cid for cid in cluster_ids if cluster_labels[cid] == UNLABELED]

    # A deliberate --k override (not an auto/sweep pick) is treated as "this is
    # the one I'm going with" and marked active, so it's unambiguous which
    # model_id/label file to keep using even after other k's have been tried
    # in the same output_dir.
    is_active = k is not None
    active_model_path = None
    if is_active:
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

    return {
        "crs": info.crs,
        "candidate_tile_count": grid_cells,
        "tile_count": len(tiles),
        "tiles_dropped_nodata": dropped,
        "k_sweep": [{"k": r["k"], "silhouette": r["silhouette"]} for r in sweep_results],
        "chosen_k": chosen_k,
        "k_selection_method": selection_method,
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
    }
