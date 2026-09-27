"""
Diagnostic: compares ResNet18 (ImageNet) vs DINOv2 (self-supervised) as the
deep-embedding component of the classification feature vector, on the same
tiles from the same orthomosaic, using the same k-sweep + silhouette scoring
already used to pick k for the real pipeline.

Both variants keep VARI + texture appended (fair comparison of just the
512/384-dim embedding swap, not a different feature set entirely).

Usage:
    python scripts/compare_feature_extractors.py path/to/orthomosaic.tif
"""
from __future__ import annotations

import argparse
import sys
import time
from pathlib import Path

import numpy as np
import torch

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from classification.tiling import read_geotiff, tile_raster  # noqa: E402
from classification.features import ResNet18FeatureExtractor, compute_vari, compute_texture_density  # noqa: E402
from classification.clustering import fit_scaler, fit_pca, sweep_k, best_by_silhouette  # noqa: E402

BATCH_SIZE = 32


class DinoV2FeatureExtractor:
    """DINOv2 ViT-S/14 (self-supervised, no ImageNet classification head to
    begin with) -> 384-dim CLS-token embedding per tile. Smallest DINOv2
    variant, chosen for CPU feasibility."""

    def __init__(self) -> None:
        self._net = torch.hub.load("facebookresearch/dinov2", "dinov2_vits14", trust_repo=True)
        self._net.eval()
        self._mean = torch.tensor([0.485, 0.456, 0.406]).view(3, 1, 1)
        self._std = torch.tensor([0.229, 0.224, 0.225]).view(3, 1, 1)

    def _preprocess(self, rgb_uint8: np.ndarray) -> torch.Tensor:
        t = torch.from_numpy(rgb_uint8).permute(2, 0, 1).float() / 255.0
        return (t - self._mean) / self._std

    @torch.inference_mode()
    def extract_batch(self, tiles_rgb_uint8: list[np.ndarray]) -> np.ndarray:
        batch = torch.stack([self._preprocess(t) for t in tiles_rgb_uint8])
        out = self._net.forward_features(batch)
        return out["x_norm_clstoken"].numpy()


def build_features(tiles, extractor, label: str) -> np.ndarray:
    rows = []
    t0 = time.time()
    for start in range(0, len(tiles), BATCH_SIZE):
        batch = tiles[start : start + BATCH_SIZE]
        embed_batch = extractor.extract_batch([t.image for t in batch])
        for i, tile in enumerate(batch):
            vari = compute_vari(tile.image)
            texture = compute_texture_density(tile.image)
            rows.append(np.concatenate([embed_batch[i], [vari], [texture]]))
        print(f"  [{label}] features: {min(start + BATCH_SIZE, len(tiles))}/{len(tiles)}", flush=True)
    print(f"  [{label}] feature extraction took {time.time()-t0:.1f}s, dim={rows[0].shape[0]}")
    return np.stack(rows)


def evaluate(feature_matrix: np.ndarray, label: str, pca_variance: float = 0.90) -> list[dict]:
    _, normalized = fit_scaler(feature_matrix)
    _, reduced = fit_pca(normalized, pca_variance)
    print(f"  [{label}] reduced to {reduced.shape[1]} dims after PCA(var={pca_variance})")
    results = sweep_k(reduced, list(range(4, 11)))
    for r in results:
        print(f"  [{label}] k={r['k']}: silhouette={r['silhouette']:.4f}")
    return results


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("geotiff")
    parser.add_argument("--tile-size", type=int, default=224)
    parser.add_argument("--max-nodata-fraction", type=float, default=0.3)
    parser.add_argument("--pca-variance", type=float, default=0.90)
    args = parser.parse_args()

    print(f"Reading {args.geotiff} ...")
    image, mask, info = read_geotiff(args.geotiff)
    tiles = tile_raster(image, mask, info, tile_size=args.tile_size, max_nodata_fraction=args.max_nodata_fraction)
    print(f"{len(tiles)} tiles after nodata filtering")

    print("\n=== ResNet18 (ImageNet, current default) ===")
    resnet_features = build_features(tiles, ResNet18FeatureExtractor(), "resnet18")
    resnet_results = evaluate(resnet_features, "resnet18", args.pca_variance)
    resnet_best = best_by_silhouette(resnet_results)

    print("\n=== DINOv2 ViT-S/14 (self-supervised) ===")
    dino_features = build_features(tiles, DinoV2FeatureExtractor(), "dinov2")
    dino_results = evaluate(dino_features, "dinov2", args.pca_variance)
    dino_best = best_by_silhouette(dino_results)

    print("\n=== Summary ===")
    print(f"ResNet18 best: k={resnet_best['k']}, silhouette={resnet_best['silhouette']:.4f}")
    print(f"DINOv2   best: k={dino_best['k']}, silhouette={dino_best['silhouette']:.4f}")
    winner = "DINOv2" if dino_best["silhouette"] > resnet_best["silhouette"] else "ResNet18"
    print(f"Higher silhouette (tighter, better-separated clusters): {winner}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
