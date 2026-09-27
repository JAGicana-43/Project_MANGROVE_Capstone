"""Ties a fitted clustering model (StandardScaler + KMeans) to a stable
model_id, and manages the human-readable cluster label file that goes with it.

Cluster IDs are not stable across fits -- a different k, a different input
file, or a different nodata threshold can reshuffle which number means what.
So a label file is only ever reused when it was written for the *exact* same
fit; anything else (no file yet, or a file from a different fit) gets a
fresh all-UNLABELED template instead of silently applying stale names.
"""
from __future__ import annotations

import hashlib
import json
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path

import joblib
from sklearn.cluster import KMeans
from sklearn.decomposition import PCA
from sklearn.preprocessing import StandardScaler

UNLABELED = "UNLABELED"


def compute_model_id(
    geotiff_path: Path,
    tile_size: int,
    k: int,
    max_nodata_fraction: float,
    feature_dim: int,
    random_state: int,
    feature_extractor_name: str,
    pca_variance: float | None = None,
) -> str:
    """Fingerprints everything that determines the fit. File identity uses
    path + size + mtime rather than a full content hash -- cheap, and good
    enough for a local prototype tool (re-fitting on genuinely different
    data with an unchanged mtime isn't a realistic scenario here). pca_variance
    is part of the fit (it changes what feature space K-Means sees), so it's
    included -- a run with PCA on gets its own model_id, never silently
    reusing labels fit against the un-reduced feature space or a different
    variance target."""
    stat = geotiff_path.stat()
    payload = {
        "geotiff_name": geotiff_path.name,
        "geotiff_size": stat.st_size,
        "geotiff_mtime": int(stat.st_mtime),
        "tile_size": tile_size,
        "k": k,
        "max_nodata_fraction": max_nodata_fraction,
        "feature_dim": feature_dim,
        "random_state": random_state,
        "feature_extractor": feature_extractor_name,
        "pca_variance": pca_variance,
    }
    digest = hashlib.sha256(json.dumps(payload, sort_keys=True).encode()).hexdigest()
    return digest[:12]


@dataclass(frozen=True)
class ModelArtifact:
    model_id: str
    model_path: Path
    labels_path: Path


def artifact_paths(output_dir: Path, model_id: str) -> ModelArtifact:
    """Model + label file live side by side in the same folder, sharing a
    filename stem, so the pairing is obvious from a file listing alone."""
    model_dir = output_dir / "model_artifacts"
    model_dir.mkdir(parents=True, exist_ok=True)
    return ModelArtifact(
        model_id=model_id,
        model_path=model_dir / f"model_{model_id}.joblib",
        labels_path=model_dir / f"model_{model_id}.labels.json",
    )


def save_model(
    artifact: ModelArtifact,
    scaler: StandardScaler,
    kmeans: KMeans,
    cluster_ids: list[int],
    pca: PCA | None = None,
) -> None:
    """pca is None when PCA was disabled for this fit -- callers must apply
    the same scaler -> (pca if not None) -> kmeans chain to classify new data
    with this exact calibration."""
    joblib.dump(
        {
            "model_id": artifact.model_id,
            "scaler": scaler,
            "pca": pca,
            "kmeans": kmeans,
            "cluster_ids": cluster_ids,
            "saved_at": datetime.now(timezone.utc).isoformat(),
        },
        artifact.model_path,
    )


def load_or_init_labels(artifact: ModelArtifact, cluster_ids: list[int], reset: bool = False) -> dict[int, str]:
    """reset=True forces a fresh UNLABELED template even if a matching label
    file already exists -- an explicit, opt-in way to discard prior guesses
    and start a real labeling pass, distinct from the automatic fresh-template
    path (which only fires when there's no matching file at all)."""
    if not reset and artifact.labels_path.is_file():
        try:
            data = json.loads(artifact.labels_path.read_text())
        except (json.JSONDecodeError, OSError):
            data = {}
        if data.get("model_id") == artifact.model_id:
            saved_labels = data.get("labels", {})
            return {cid: saved_labels.get(str(cid), UNLABELED) for cid in cluster_ids}

    template = {
        "model_id": artifact.model_id,
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "instructions": (
            "Edit the values below to name each cluster (e.g. 'mangrove_canopy', "
            "'mudflat', 'water_channel'), then re-run against the same GeoTIFF/k "
            "to have the names joined onto results.csv and shown on the cluster map."
        ),
        "labels": {str(cid): UNLABELED for cid in cluster_ids},
    }
    artifact.labels_path.write_text(json.dumps(template, indent=2))
    return {cid: UNLABELED for cid in cluster_ids}


ACTIVE_MODEL_FILENAME = "ACTIVE_MODEL.json"


def set_active_model(output_dir: Path, artifact: ModelArtifact, k: int) -> Path:
    """Marks one model artifact as 'the' current one for this output_dir, so a
    deliberate choice (e.g. picking k=5 over the silhouette-optimal k=4)
    doesn't get lost among other model_ids sitting in the same folder."""
    active_path = output_dir / "model_artifacts" / ACTIVE_MODEL_FILENAME
    active_path.write_text(
        json.dumps(
            {
                "model_id": artifact.model_id,
                "k": k,
                "model_path": artifact.model_path.name,
                "labels_path": artifact.labels_path.name,
                "set_at": datetime.now(timezone.utc).isoformat(),
            },
            indent=2,
        )
    )
    return active_path


def get_active_model(output_dir: Path) -> dict | None:
    active_path = output_dir / "model_artifacts" / ACTIVE_MODEL_FILENAME
    if not active_path.is_file():
        return None
    try:
        return json.loads(active_path.read_text())
    except (json.JSONDecodeError, OSError):
        return None
