"""
Visual companion to compare_feature_extractors.py: fits both extractors at a
fixed k (default 5, matching the active model's operating point rather than
the pure-silhouette-best k) and builds one contact-sheet PNG showing sample
tiles per cluster for each method, side by side, for a real visual QA check
-- not just a silhouette number.

Usage:
    python scripts/visual_compare_feature_extractors.py path/to/orthomosaic.tif --k 5
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

import cv2
import numpy as np
import torch

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from classification.tiling import read_geotiff, tile_raster  # noqa: E402
from classification.features import ResNet18FeatureExtractor, compute_vari, compute_texture_density  # noqa: E402
from classification.clustering import fit_scaler, fit_pca, fit_clusters  # noqa: E402
from scripts.compare_feature_extractors import DinoV2FeatureExtractor, build_features  # noqa: E402

THUMB = 112
SAMPLES_PER_CLUSTER = 5
LABEL_WIDTH = 220
ROW_PAD = 6


def cluster_tiles(feature_matrix: np.ndarray, k: int, pca_variance: float = 0.90) -> np.ndarray:
    _, normalized = fit_scaler(feature_matrix)
    _, reduced = fit_pca(normalized, pca_variance)
    _, labels, score = fit_clusters(reduced, k=k)
    print(f"  silhouette at k={k}: {score:.4f}")
    return labels


def build_method_block(tiles, labels, k: int, title: str) -> np.ndarray:
    by_cluster: dict[int, list[int]] = {}
    for idx, label in enumerate(labels):
        by_cluster.setdefault(int(label), []).append(idx)

    row_height = THUMB + ROW_PAD
    title_height = 36
    width = LABEL_WIDTH + THUMB * SAMPLES_PER_CLUSTER
    height = title_height + row_height * k

    block = np.full((height, width, 3), 255, dtype=np.uint8)
    cv2.putText(block, title, (8, 26), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (20, 20, 20), 2, cv2.LINE_AA)

    for cluster_id in range(k):
        indices = by_cluster.get(cluster_id, [])
        y0 = title_height + cluster_id * row_height
        label_text = f"cluster {cluster_id} (n={len(indices)})"
        cv2.putText(block, label_text, (8, y0 + THUMB // 2 + 6), cv2.FONT_HERSHEY_SIMPLEX, 0.55, (20, 20, 20), 1, cv2.LINE_AA)

        for col, idx in enumerate(indices[:SAMPLES_PER_CLUSTER]):
            tile_bgr = cv2.cvtColor(tiles[idx].image, cv2.COLOR_RGB2BGR)
            thumb = cv2.resize(tile_bgr, (THUMB, THUMB))
            x0 = LABEL_WIDTH + col * THUMB
            block[y0 : y0 + THUMB, x0 : x0 + THUMB] = thumb
            cv2.rectangle(block, (x0, y0), (x0 + THUMB - 1, y0 + THUMB - 1), (200, 200, 200), 1)

    return block


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("geotiff")
    parser.add_argument("--k", type=int, default=5)
    parser.add_argument("--tile-size", type=int, default=224)
    parser.add_argument("--max-nodata-fraction", type=float, default=0.3)
    parser.add_argument("--output", default="classification_output_real/resnet_vs_dinov2_visual.png")
    args = parser.parse_args()

    print(f"Reading {args.geotiff} ...")
    image, mask, info = read_geotiff(args.geotiff)
    tiles = tile_raster(image, mask, info, tile_size=args.tile_size, max_nodata_fraction=args.max_nodata_fraction)
    print(f"{len(tiles)} tiles after nodata filtering")

    print("ResNet18: extracting + clustering ...")
    resnet_features = build_features(tiles, ResNet18FeatureExtractor(), "resnet18")
    resnet_labels = cluster_tiles(resnet_features, args.k)
    resnet_block = build_method_block(tiles, resnet_labels, args.k, "ResNet18 (ImageNet)")

    print("DINOv2: extracting + clustering ...")
    dino_features = build_features(tiles, DinoV2FeatureExtractor(), "dinov2")
    dino_labels = cluster_tiles(dino_features, args.k)
    dino_block = build_method_block(tiles, dino_labels, args.k, "DINOv2 ViT-S/14")

    gap = np.full((16, resnet_block.shape[1], 3), 230, dtype=np.uint8)
    combined = np.vstack([resnet_block, gap, dino_block])

    output_path = Path(args.output)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    cv2.imwrite(str(output_path), combined)
    print(f"\nSaved visual comparison to: {output_path.resolve()}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
