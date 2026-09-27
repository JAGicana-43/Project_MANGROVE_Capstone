"""Diagnostic: compares the current ImageNet-pretrained ResNet18 against a
ResNet18 pretrained on Sentinel-2 RGB imagery (torchgeo's SSL4EO-S12 MoCo
weights) as the deep-embedding component, on the same tiles from the same
orthomosaic, using the same k-sweep + silhouette scoring already used to
pick k for the real pipeline.

Domain-mismatch caveat this test is specifically checking: the Sentinel-2
weights' own published preprocessing expects raw reflectance values (mean=0,
std=10000), calibrated for 10m-resolution atmospherically-corrected
satellite imagery -- not a standard 8-bit drone RGB photo. This script uses
ImageNet-style 0-1 + mean/std normalization instead (the same preprocessing
already applied to the current ResNet18) so both models see comparable
input; if the Sentinel-2 weights still don't outperform ImageNet under
their own native reflectance-scale preprocessing, that's a second reason to
expect the domain gap (10m satellite composite vs. cm-scale drone photo) is
real, not just a normalization artifact.

Usage:
    python scripts/compare_sentinel2_resnet.py path/to/orthomosaic.tif
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
from classification.features import ResNet18FeatureExtractor  # noqa: E402
from classification.clustering import fit_scaler, fit_pca, sweep_k, best_by_silhouette  # noqa: E402

BATCH_SIZE = 32


class Sentinel2ResNet18FeatureExtractor:
    """ResNet18 pretrained on Sentinel-2 RGB bands (SSL4EO-S12, MoCo) ->
    512-dim embedding per tile. Same architecture/output size as the
    current ImageNet ResNet18, so it's a drop-in swap if it wins this
    comparison."""

    def __init__(self) -> None:
        from torchgeo.models import ResNet18_Weights, resnet18

        net = resnet18(weights=ResNet18_Weights.SENTINEL2_RGB_MOCO)
        net.fc = torch.nn.Identity()
        net.eval()
        self._net = net
        # ImageNet-style normalization, not the weights' own raw-reflectance
        # transform -- see module docstring.
        self._mean = torch.tensor([0.485, 0.456, 0.406]).view(3, 1, 1)
        self._std = torch.tensor([0.229, 0.224, 0.225]).view(3, 1, 1)

    def _preprocess(self, rgb_uint8: np.ndarray) -> torch.Tensor:
        t = torch.from_numpy(rgb_uint8).permute(2, 0, 1).float() / 255.0
        return (t - self._mean) / self._std

    @torch.inference_mode()
    def extract_batch(self, tiles_rgb_uint8: list[np.ndarray]) -> np.ndarray:
        batch = torch.stack([self._preprocess(t) for t in tiles_rgb_uint8])
        return self._net(batch).numpy()


def build_features(tiles, extractor, label: str) -> np.ndarray:
    rows = []
    t0 = time.time()
    for start in range(0, len(tiles), BATCH_SIZE):
        batch = tiles[start : start + BATCH_SIZE]
        embed_batch = extractor.extract_batch([t.image for t in batch])
        rows.extend(embed_batch)
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
    imagenet_features = build_features(tiles, ResNet18FeatureExtractor(), "imagenet")
    imagenet_results = evaluate(imagenet_features, "imagenet", args.pca_variance)
    imagenet_best = best_by_silhouette(imagenet_results)

    print("\n=== ResNet18 (Sentinel-2 RGB, SSL4EO-S12 MoCo) ===")
    s2_features = build_features(tiles, Sentinel2ResNet18FeatureExtractor(), "sentinel2")
    s2_results = evaluate(s2_features, "sentinel2", args.pca_variance)
    s2_best = best_by_silhouette(s2_results)

    print("\n=== Summary ===")
    print(f"ImageNet ResNet18   best: k={imagenet_best['k']}, silhouette={imagenet_best['silhouette']:.4f}")
    print(f"Sentinel-2 ResNet18 best: k={s2_best['k']}, silhouette={s2_best['silhouette']:.4f}")
    winner = "Sentinel-2 ResNet18" if s2_best["silhouette"] > imagenet_best["silhouette"] else "ImageNet ResNet18"
    print(f"Higher silhouette (tighter, better-separated clusters): {winner}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
