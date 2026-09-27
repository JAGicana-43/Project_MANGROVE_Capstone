"""ResNet18 feature extraction, VARI/ExG/GLI vegetation indices, and a
hand-crafted texture-density measure.

Feature extractor swapped 2026-09-02: was ImageNet-pretrained (generic
photo classification -- cats, cars, household objects), now uses
torchgeo's ResNet18 pretrained on Sentinel-2 RGB imagery (SSL4EO-S12,
self-supervised MoCo). ImageNet features were never trained to distinguish
land-cover classes; they happened to separate water/vegetation/soil only
because those are visually distinct enough for a generic classifier to
notice incidentally. Verified directly, not assumed: on the real test
orthomosaic, silhouette score (cluster separation quality) went from
~0.14 (ImageNet) to ~0.36 (Sentinel-2) -- roughly 2.5x -- and the resulting
clusters were visually confirmed to correspond to distinct, coherent
land-cover sub-types (multiple mud/soil textures, two water variants, one
clean vegetation cluster) rather than an arbitrary statistical split. See
scripts/compare_sentinel2_resnet.py for the reproducible comparison.

Domain-mismatch note: the Sentinel-2 weights' own published preprocessing
expects raw satellite reflectance values (mean=0, std=10000), calibrated
for 10m-resolution atmospherically-corrected imagery -- not an 8-bit drone
photo. This uses ImageNet-style 0-1 + mean/std normalization instead (same
preprocessing the old extractor used), which empirically transfers better
to this data than the weights' own native normalization would."""
from __future__ import annotations

import cv2
import numpy as np
import torch
from torchgeo.models import ResNet18_Weights, resnet18

_IMAGENET_MEAN = [0.485, 0.456, 0.406]
_IMAGENET_STD = [0.229, 0.224, 0.225]


class ResNet18FeatureExtractor:
    """ResNet18 pretrained on Sentinel-2 RGB bands (SSL4EO-S12, MoCo), head
    removed -> 512-dim embedding. Same architecture/output size as the
    previous ImageNet version, so nothing downstream (PCA, clustering,
    caching) needed to change."""

    def __init__(self) -> None:
        net = resnet18(weights=ResNet18_Weights.SENTINEL2_RGB_MOCO)
        net.fc = torch.nn.Identity()
        net.eval()
        self._net = net
        self._mean = torch.tensor(_IMAGENET_MEAN).view(3, 1, 1)
        self._std = torch.tensor(_IMAGENET_STD).view(3, 1, 1)

    def _preprocess(self, rgb_uint8: np.ndarray) -> torch.Tensor:
        tensor = torch.from_numpy(rgb_uint8).permute(2, 0, 1).float() / 255.0
        return (tensor - self._mean) / self._std

    @torch.inference_mode()
    def extract(self, rgb_uint8: np.ndarray) -> np.ndarray:
        tensor = self._preprocess(rgb_uint8).unsqueeze(0)
        return self._net(tensor).squeeze(0).numpy()

    @torch.inference_mode()
    def extract_batch(self, tiles_rgb_uint8: list[np.ndarray]) -> np.ndarray:
        batch = torch.stack([self._preprocess(tile) for tile in tiles_rgb_uint8])
        return self._net(batch).numpy()


def compute_vari(rgb_uint8: np.ndarray) -> float:
    """Visible Atmospherically Resistant Index: (G - R) / (G + R - B)."""
    rgb = rgb_uint8.astype(np.float32)
    r, g, b = rgb[..., 0], rgb[..., 1], rgb[..., 2]
    denom = g + r - b
    denom = np.where(np.abs(denom) < 1e-6, 1e-6, denom)
    return float(np.mean((g - r) / denom))


def compute_exg(rgb_uint8: np.ndarray) -> float:
    """Excess Green Index: mean(2G - R - B) over 0-1 normalized pixels. A
    second, independent RGB-only vegetation signal alongside VARI -- widely
    used in agricultural/vegetation remote sensing when no NIR band is
    available. Not a replacement for VARI: ExG is an absolute measure
    (sensitive to overall brightness/exposure), VARI is a ratio (normalized
    against it) -- giving the clustering both a raw and a normalized view
    of the same underlying "how green is this" signal."""
    rgb = rgb_uint8.astype(np.float32) / 255.0
    r, g, b = rgb[..., 0], rgb[..., 1], rgb[..., 2]
    return float(np.mean(2 * g - r - b))


def compute_gli(rgb_uint8: np.ndarray) -> float:
    """Green Leaf Index: (2G - R - B) / (2G + R + B). Like VARI, ratio-
    normalized against overall brightness so it's less sensitive to
    lighting/exposure differences across an orthomosaic than ExG alone --
    a third, differently-weighted vegetation signal."""
    rgb = rgb_uint8.astype(np.float32)
    r, g, b = rgb[..., 0], rgb[..., 1], rgb[..., 2]
    denom = 2 * g + r + b
    denom = np.where(np.abs(denom) < 1e-6, 1e-6, denom)
    return float(np.mean((2 * g - r - b) / denom))


def compute_texture_density(rgb_uint8: np.ndarray) -> float:
    """Laplacian variance: a standard sharpness/edge-density measure. More
    edges/structural complexity in a patch (leaf boundaries, gaps, shadow
    lines) -> higher variance; smoother/more homogeneous texture -> lower.
    Meant as an explicit, hand-built stand-in for "how visually dense/varied
    does this canopy patch look" -- something the generic ImageNet ResNet18
    embedding was never trained to encode."""
    gray = cv2.cvtColor(rgb_uint8, cv2.COLOR_RGB2GRAY)
    laplacian = cv2.Laplacian(gray, cv2.CV_32F)
    return float(laplacian.var())
