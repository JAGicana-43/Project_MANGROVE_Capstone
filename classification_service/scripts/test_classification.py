"""
Standalone CLI to prototype/validate the tile-classification pipeline against
a local GeoTIFF orthomosaic. Not a Flask route, not wired into app.py.

Usage:
    python scripts/test_classification.py path/to/orthomosaic.tif
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from classification.pipeline import run_classification  # noqa: E402


def main() -> int:
    parser = argparse.ArgumentParser(description="Prototype tile-classification pipeline.")
    parser.add_argument("geotiff", help="Path to a local GeoTIFF orthomosaic.")
    parser.add_argument("--output-dir", default="classification_output", help="Where to write results + sample tiles.")
    parser.add_argument("--tile-size", type=int, default=224)
    parser.add_argument(
        "--k",
        type=int,
        default=None,
        help="Force this many K-Means clusters, overriding the silhouette-based auto-pick from the k-sweep.",
    )
    parser.add_argument("--k-min", type=int, default=4, help="Lowest k to try in the silhouette sweep. Default 4.")
    parser.add_argument("--k-max", type=int, default=10, help="Highest k to try in the silhouette sweep. Default 10.")
    parser.add_argument("--samples-per-cluster", type=int, default=5)
    parser.add_argument("--format", choices=["csv", "json"], default="csv")
    parser.add_argument(
        "--max-nodata-fraction",
        type=float,
        default=0.3,
        help="Drop tiles whose nodata/transparent pixel share exceeds this (0-1). Default 0.3.",
    )
    parser.add_argument(
        "--reset-labels",
        action="store_true",
        help="Discard any existing labels for this model_id and write a fresh UNLABELED template instead of reusing them.",
    )
    parser.add_argument(
        "--grid-cell-size",
        type=int,
        default=1120,
        help="Sampling-grid cell size in source pixels (must be >= tile size). Default 1120 (5x5 tiles).",
    )
    parser.add_argument(
        "--grid-min-tiles",
        type=int,
        default=5,
        help="Minimum valid tiles a grid cell needs before it gets a majority label, else INSUFFICIENT_DATA. Default 5.",
    )
    args = parser.parse_args()

    geotiff_path = Path(args.geotiff)
    if not geotiff_path.is_file():
        print(f"No such file: {geotiff_path}", file=sys.stderr)
        return 1

    summary = run_classification(
        geotiff_path,
        args.output_dir,
        tile_size=args.tile_size,
        k=args.k,
        k_sweep_min=args.k_min,
        k_sweep_max=args.k_max,
        samples_per_cluster=args.samples_per_cluster,
        results_format=args.format,
        max_nodata_fraction=args.max_nodata_fraction,
        reset_labels=args.reset_labels,
        grid_cell_size=args.grid_cell_size,
        grid_min_tiles=args.grid_min_tiles,
    )

    print(f"CRS: {summary['crs']}")
    print(f"Candidate tiles before nodata filtering: {summary['candidate_tile_count']}")
    print(f"Valid tiles processed (after nodata filtering): {summary['tile_count']}")
    print(f"Tiles dropped as nodata: {summary['tiles_dropped_nodata']}")
    print("Silhouette sweep:")
    for entry in summary["k_sweep"]:
        marker = "  <- chosen" if entry["k"] == summary["chosen_k"] else ""
        print(f"  k={entry['k']}: silhouette={entry['silhouette']:.4f}{marker}")
    print(f"Chosen k: {summary['chosen_k']} ({summary['k_selection_method']})")
    print(f"Model id: {summary['model_id']}")
    print(f"Model artifact: {summary['model_path']}")
    print(f"Cluster label file: {summary['labels_path']}")
    if summary["is_active"]:
        print(f"Marked ACTIVE for this output dir -> {summary['active_model_path']}")
    else:
        print("Not marked active (auto/sweep pick) -- pass --k explicitly to mark a model as active.")
    print("Tiles per cluster:")
    for cluster, count in sorted(summary["tiles_per_cluster"].items()):
        r, g, b = summary["cluster_colors"][cluster]
        label = summary["cluster_labels"][cluster]
        print(f"  cluster {cluster}: {count} tiles -- {label}  (map color RGB {r},{g},{b})")
    print("  unprocessed/nodata tiles on map: RGB 60,60,60 (dark gray)")
    if summary["unlabeled_clusters"]:
        ids = ", ".join(str(c) for c in summary["unlabeled_clusters"])
        print(
            f"WARNING: {len(summary['unlabeled_clusters'])} cluster(s) still UNLABELED ({ids}). "
            f"Edit {summary['labels_path']} and re-run against the same input/k to apply names."
        )
    print(f"Silhouette score: {summary['silhouette_score']:.4f}")
    print(f"Results saved to: {summary['results_path']}")
    print(f"Sample tile PNGs saved to: {summary['sample_tiles_dir']}")
    print(f"Cluster map PNG saved to: {summary['cluster_map_path']}")
    print(
        f"Sampling grid: {summary['grid_cell_count']} cells at {summary['grid_cell_size']}px "
        f"(min {summary['grid_min_tiles']} tiles/cell), {summary['grid_insufficient_cell_count']} flagged INSUFFICIENT_DATA"
    )
    print(f"Grid results saved to: {summary['grid_results_path']}")
    print(f"Grid map PNG saved to: {summary['grid_map_path']}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
