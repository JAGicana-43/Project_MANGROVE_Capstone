"""
Standalone CLI to prototype/validate the tile-classification pipeline against
a local GeoTIFF orthomosaic. Not a Flask route, not wired into app.py.

Usage:
    python scripts/test_classification.py path/to/orthomosaic.tif
"""
from __future__ import annotations

import argparse
import json
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
    parser.add_argument(
        "--smoothing-window",
        type=int,
        default=3,
        help="Majority-filter window in tiles (e.g. 3 = 3x3 neighborhood) applied to cluster assignments before "
        "results/maps/grid/suitability are built. Does not affect the saved model or silhouette score. "
        "Set to 1 to disable. Default 3.",
    )
    parser.add_argument(
        "--pca-variance",
        type=float,
        default=0.90,
        help="Reduce features to the smallest number of PCA components that retain this much variance "
        "(0-1) before clustering, to stop hundreds of largely-irrelevant ImageNet dimensions from "
        "diluting distance calculations. Set to 0 to disable and cluster on the full feature space. Default 0.90.",
    )
    parser.add_argument(
        "--feature-set",
        choices=["full", "texture_vari"],
        default="full",
        help="'full' = ResNet18+VARI+texture (514-dim, default). 'texture_vari' = skip ResNet entirely and "
        "cluster on just [VARI, texture] (2-dim) -- diagnostic to check whether those two signals carry a "
        "usable split on their own without being diluted by ResNet's dimension count.",
    )
    parser.add_argument(
        "--summary-json",
        default=None,
        help="Write the full summary dict (everything printed below, plus paths not shown) as JSON to this "
        "path -- for a caller (e.g. a web backend) to read machine-readable results instead of parsing stdout.",
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
        smoothing_window=args.smoothing_window,
        pca_variance=args.pca_variance,
        feature_set=args.feature_set,
    )

    print(f"CRS: {summary['crs']}")
    print(f"Feature set: {summary['feature_set']}")
    if summary["pca_components"] is not None:
        print(
            f"PCA: {summary['feature_dim_original']} -> {summary['pca_components']} dims "
            f"(retaining {summary['pca_variance_retained']:.1%} variance, target {summary['pca_variance_target']:.0%})"
        )
    else:
        print("PCA: disabled")
    print(f"Candidate tiles before nodata filtering: {summary['candidate_tile_count']}")
    print(f"Valid tiles processed (after nodata filtering): {summary['tile_count']}")
    print(f"Tiles dropped as nodata: {summary['tiles_dropped_nodata']}")
    print("Silhouette sweep:")
    for entry in summary["k_sweep"]:
        marker = "  <- chosen" if entry["k"] == summary["chosen_k"] else ""
        print(f"  k={entry['k']}: silhouette={entry['silhouette']:.4f}{marker}")
    print(f"Chosen k: {summary['chosen_k']} ({summary['k_selection_method']})")
    if summary["smoothing_window"] > 1:
        print(
            f"Spatial smoothing: window={summary['smoothing_window']}x{summary['smoothing_window']} tiles, "
            f"{summary['tiles_changed_by_smoothing']}/{summary['tile_count']} tiles reassigned"
        )
    else:
        print("Spatial smoothing: disabled")
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

    print("Suitability scoring config:")
    for label, score in summary["suitability_scores_config"].items():
        print(f"  {label}: {'excluded' if score is None else score}")
    score_summary = summary["score_summary"]
    print(
        f"Suitability: {score_summary['eligible_count']} eligible cells, "
        f"{score_summary['excluded_count']} excluded (of {score_summary['total_cells']} total)"
    )
    print("  Score tiers (eligible cells):")
    for tier in score_summary["tiers"]:
        print(f"    score {tier['score']} -- {tier['label']}: {tier['count']} cells")
    if score_summary["excluded_reasons"]:
        print("  Excluded breakdown:")
        for reason, count in score_summary["excluded_reasons"].items():
            print(f"    {reason}: {count} cells")
    print(f"Suitability results saved to: {summary['suitability_results_path']}")
    print(f"Suitability map PNG saved to: {summary['suitability_map_path']}")
    print(f"Web overlay PNG saved to: {summary['web_overlay_path']}")

    if args.summary_json:
        Path(args.summary_json).write_text(json.dumps(summary, indent=2))
        print(f"Summary JSON saved to: {args.summary_json}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
