"""ResNet18 (ImageNet weights, CPU-only) feature extraction, and VARI."""
from __future__ import annotations

import numpy as np
import torch
from torchvision import models, transforms

_IMAGENET_MEAN = [0.485, 0.456, 0.406]
_IMAGENET_STD = [0.229, 0.224, 0.225]


class ResNet18FeatureExtractor:
    """Pretrained ResNet18 with its classification head removed -> 512-dim embedding."""

    def __init__(self) -> None:
        net = models.resnet18(weights=models.ResNet18_Weights.IMAGENET1K_V1)
        net.fc = torch.nn.Identity()
        net.eval()
        self._net = net
        self._preprocess = transforms.Compose(
            [
                transforms.ToTensor(),
                transforms.Normalize(mean=_IMAGENET_MEAN, std=_IMAGENET_STD),
            ]
        )

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
