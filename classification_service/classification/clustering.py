"""Feature normalization + K-Means clustering.

Returns the fitted StandardScaler/KMeans objects (not just labels) so the
caller can persist them as a model artifact -- that's what a cluster label
file gets tied to, since cluster IDs aren't stable across different fits.
"""
from __future__ import annotations

import math

import numpy as np
from sklearn.cluster import KMeans
from sklearn.decomposition import PCA
from sklearn.metrics import silhouette_score
from sklearn.preprocessing import StandardScaler


def fit_scaler(features: np.ndarray) -> tuple[StandardScaler, np.ndarray]:
    scaler = StandardScaler()
    normalized = scaler.fit_transform(features)
    return scaler, normalized


def fit_pca(normalized_features: np.ndarray, variance_ratio: float | None, random_state: int = 42) -> tuple[PCA | None, np.ndarray]:
    """Cuts dimensionality before clustering, so a handful of retained
    ResNet-derived directions don't get diluted across hundreds of
    largely-redundant/irrelevant ImageNet dimensions in Euclidean distance.
    variance_ratio in (0, 1] = keep enough components to explain that much
    variance; None or <=0 disables PCA and returns the input unchanged."""
    if not variance_ratio or variance_ratio <= 0:
        return None, normalized_features
    pca = PCA(n_components=variance_ratio, random_state=random_state)
    reduced = pca.fit_transform(normalized_features)
    return pca, reduced


def fit_clusters(normalized_features: np.ndarray, k: int = 5, random_state: int = 42) -> tuple[KMeans, np.ndarray, float]:
    kmeans = KMeans(n_clusters=k, n_init=10, random_state=random_state)
    labels = kmeans.fit_predict(normalized_features)
    score = silhouette_score(normalized_features, labels) if len(np.unique(labels)) > 1 else float("nan")
    return kmeans, labels, float(score)


def sweep_k(normalized_features: np.ndarray, k_values: list[int], random_state: int = 42) -> list[dict]:
    """Fits one K-Means model per k so the silhouette score can be compared
    across a range, instead of committing to a single forced k up front."""
    results = []
    for k in k_values:
        kmeans, labels, score = fit_clusters(normalized_features, k=k, random_state=random_state)
        results.append({"k": k, "kmeans": kmeans, "labels": labels, "silhouette": score})
    return results


def best_by_silhouette(sweep_results: list[dict]) -> dict:
    return max(sweep_results, key=lambda r: r["silhouette"] if not math.isnan(r["silhouette"]) else float("-inf"))
