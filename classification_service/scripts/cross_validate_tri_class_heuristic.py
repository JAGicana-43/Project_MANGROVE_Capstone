"""Stratified k-fold cross-validation for the tri-class auto-labeling
heuristic (classification/cluster_labeling.py).

Why this exists, distinct from calibrate_tri_class_heuristic.py: that script
picks threshold constants and reports accuracy using the SAME 25 tiles for
both -- a real methodology weakness (there's no evidence those thresholds
generalize to a tile the heuristic didn't get to "see" while being tuned).
This script fixes that: it splits the labeled tiles into K folds, tunes
thresholds using only the other folds each time, and reports accuracy only
on tiles that were held out during tuning. The reported numbers here are the
ones that belong in the paper's accuracy section, not the ones from the
single-split script.

Ground truth is the same direct visual inspection already done for
calibrate_tri_class_heuristic.py: cluster0/1/3 = vegetation, cluster2 =
bare_soil, cluster4 = open_water, from the real orthomosaic currently in
this project's database.

Not part of the pipeline -- a one-off validation/reporting tool, run when
producing the accuracy numbers for documentation, and again whenever new
sample tiles (a new orthomosaic) are added to TILE_DIR.
"""
import itertools
import random
import sys
from collections import Counter, defaultdict
from pathlib import Path

import cv2

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from classification.water_detection import land_cover_fractions

TILE_DIR = Path(sys.argv[1]) if len(sys.argv) > 1 else Path(__file__).resolve().parent.parent.parent / "classification_service" / "classification_output_real" / "sample_tiles"

KNOWN_LABELS = {
    "cluster0": "vegetation",
    "cluster1": "vegetation",
    "cluster2": "bare_soil",
    "cluster3": "vegetation",
    "cluster4": "open_water",
}

K_FOLDS = 5
RANDOM_SEED = 42

# Grid search ranges for the two threshold constants -- centered on the
# values calibrate_tri_class_heuristic.py landed on, widened enough either
# side to let cross-validation actually find something different if the
# held-out folds prefer it.
WATER_THRESHOLDS = [0.02, 0.03, 0.04, 0.05, 0.06, 0.08, 0.10]
VEGETATION_THRESHOLDS = [0.05, 0.08, 0.10, 0.12, 0.15, 0.18, 0.20, 0.25, 0.30]


def classify(water_frac: float, veg_frac: float, water_threshold: float, veg_threshold: float) -> str:
    if water_frac >= water_threshold and water_frac > veg_frac:
        return "open_water"
    if veg_frac >= veg_threshold:
        return "vegetation"
    return "bare_soil"


def load_tiles() -> list[dict]:
    tiles = []
    for f in sorted(TILE_DIR.glob("cluster*_tile*.png")):
        cluster_key = f.name.split("_tile")[0]
        true_label = KNOWN_LABELS.get(cluster_key)
        if true_label is None:
            continue
        bgr = cv2.imread(str(f))
        if bgr is None:
            continue
        rgb = cv2.cvtColor(bgr, cv2.COLOR_BGR2RGB)
        water_frac, veg_frac = land_cover_fractions(rgb)
        tiles.append({
            "file": f.name,
            "cluster": cluster_key,
            "true_label": true_label,
            "water_frac": water_frac,
            "veg_frac": veg_frac,
        })
    return tiles


def stratified_folds(tiles: list[dict], k: int) -> list[list[int]]:
    """Returns k lists of tile indices, each fold with roughly the same
    per-class proportions as the full set -- with only 5 bare_soil and 5
    open_water tiles total, a plain random split could easily leave a fold
    with zero examples of a class, making that fold's held-out accuracy
    meaningless for that class."""
    rng = random.Random(RANDOM_SEED)
    by_label: dict[str, list[int]] = defaultdict(list)
    for idx, tile in enumerate(tiles):
        by_label[tile["true_label"]].append(idx)

    folds: list[list[int]] = [[] for _ in range(k)]
    for label, indices in by_label.items():
        shuffled = indices[:]
        rng.shuffle(shuffled)
        for i, idx in enumerate(shuffled):
            folds[i % k].append(idx)
    return folds


def best_thresholds(tiles: list[dict], indices: list[int]) -> tuple[float, float, float]:
    """Grid search: returns (water_threshold, veg_threshold, training_accuracy)
    that scores best on the given subset of tiles."""
    best = (WATER_THRESHOLDS[0], VEGETATION_THRESHOLDS[0], -1.0)
    for water_t, veg_t in itertools.product(WATER_THRESHOLDS, VEGETATION_THRESHOLDS):
        correct = sum(
            1 for idx in indices
            if classify(tiles[idx]["water_frac"], tiles[idx]["veg_frac"], water_t, veg_t) == tiles[idx]["true_label"]
        )
        accuracy = correct / len(indices)
        if accuracy > best[2]:
            best = (water_t, veg_t, accuracy)
    return best


def main() -> None:
    tiles = load_tiles()
    if not tiles:
        print(f"No labeled tiles found in {TILE_DIR}")
        return

    print(f"Loaded {len(tiles)} labeled tiles from {TILE_DIR}")
    print(f"Class distribution: {dict(Counter(t['true_label'] for t in tiles))}")
    print(f"Running {K_FOLDS}-fold stratified cross-validation...\n")

    folds = stratified_folds(tiles, K_FOLDS)
    held_out_predictions: dict[int, str] = {}
    fold_reports = []

    for fold_num in range(K_FOLDS):
        test_indices = folds[fold_num]
        train_indices = [i for f in range(K_FOLDS) if f != fold_num for i in folds[f]]

        water_t, veg_t, train_acc = best_thresholds(tiles, train_indices)

        correct = 0
        for idx in test_indices:
            predicted = classify(tiles[idx]["water_frac"], tiles[idx]["veg_frac"], water_t, veg_t)
            held_out_predictions[idx] = predicted
            correct += predicted == tiles[idx]["true_label"]

        test_acc = correct / len(test_indices)
        fold_reports.append({
            "fold": fold_num + 1,
            "train_n": len(train_indices),
            "test_n": len(test_indices),
            "water_threshold": water_t,
            "veg_threshold": veg_t,
            "train_accuracy": train_acc,
            "test_accuracy": test_acc,
        })
        print(
            f"Fold {fold_num + 1}: tuned on {len(train_indices)} tiles "
            f"(water>={water_t}, veg>={veg_t}, train acc {train_acc:.1%}) "
            f"-> held-out accuracy on {len(test_indices)} unseen tiles: {test_acc:.1%}"
        )

    # Every tile now has exactly one held-out prediction (made by a fold that
    # never saw it during tuning) -- this is the honest, non-leaked accuracy
    # figure for the paper, not an average of the per-fold numbers above
    # (which can be skewed by small per-fold test set sizes).
    total_correct = sum(1 for idx, pred in held_out_predictions.items() if pred == tiles[idx]["true_label"])
    overall_accuracy = total_correct / len(tiles)

    print(f"\n{'=' * 70}")
    print(f"OVERALL CROSS-VALIDATED TILE-LEVEL ACCURACY: {total_correct}/{len(tiles)} = {overall_accuracy:.1%}")
    print(f"{'=' * 70}\n")

    # Confusion matrix, built entirely from held-out (never-seen-during-
    # tuning) predictions.
    labels = ["bare_soil", "vegetation", "open_water"]
    matrix = {t: {p: 0 for p in labels} for t in labels}
    for idx, pred in held_out_predictions.items():
        matrix[tiles[idx]["true_label"]][pred] += 1

    print("Confusion matrix (rows = true label, columns = predicted, held-out predictions only):")
    header = f"{'':14}" + "".join(f"{p:>12}" for p in labels)
    print(header)
    for true_label in labels:
        row = f"{true_label:14}" + "".join(f"{matrix[true_label][p]:>12}" for p in labels)
        print(row)

    print("\nPer-class precision / recall / F1 (from held-out predictions):")
    for label in labels:
        tp = matrix[label][label]
        fn = sum(matrix[label][p] for p in labels if p != label)
        fp = sum(matrix[t][label] for t in labels if t != label)
        precision = tp / (tp + fp) if (tp + fp) > 0 else float("nan")
        recall = tp / (tp + fn) if (tp + fn) > 0 else float("nan")
        f1 = (2 * precision * recall / (precision + recall)) if (precision + recall) > 0 else float("nan")
        support = tp + fn
        print(f"  {label:12} precision={precision:.2f}  recall={recall:.2f}  f1={f1:.2f}  (n={support})")

    # Cluster-level accuracy from held-out predictions -- majority-voting
    # each cluster's tiles using ONLY their held-out-fold predictions (never
    # a prediction made by a fold that tuned on that same tile). This is the
    # number that actually matters for the real pipeline, since clusters
    # (not individual tiles) are what get labeled in production.
    print("\nCluster-level accuracy (majority vote of each cluster's held-out predictions):")
    by_cluster: dict[str, list[int]] = defaultdict(list)
    for idx, tile in enumerate(tiles):
        by_cluster[tile["cluster"]].append(idx)

    cluster_correct = 0
    for cluster, indices in sorted(by_cluster.items()):
        true_label = tiles[indices[0]]["true_label"]
        votes = Counter(held_out_predictions[idx] for idx in indices)
        majority = votes.most_common(1)[0][0]
        ok = majority == true_label
        cluster_correct += ok
        print(f"  {cluster}: true={true_label}, held-out votes={dict(votes)}, majority={majority} {'' if ok else '  <-- WRONG'}")

    print(f"\nCluster-level cross-validated accuracy: {cluster_correct}/{len(by_cluster)} = {cluster_correct / len(by_cluster):.1%}")

    # Final recommended thresholds: refit one more time on ALL labeled tiles
    # (not a held-out subset) -- this is the pair that should actually ship
    # in cluster_labeling.py going forward, now chosen with the same rigor
    # the cross-validation above just demonstrated, not by eyeballing one
    # printout.
    final_water_t, final_veg_t, final_train_acc = best_thresholds(tiles, list(range(len(tiles))))
    print(f"\nRecommended production thresholds (fit on all {len(tiles)} labeled tiles): "
          f"WATER_GLINT_MIN_FRACTION={final_water_t}, VEGETATION_MIN_FRACTION={final_veg_t} "
          f"(full-data accuracy {final_train_acc:.1%})")


if __name__ == "__main__":
    main()
