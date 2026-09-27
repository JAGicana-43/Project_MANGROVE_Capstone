"""Scratch script: verify classification.water_detection's per-pixel
proportional approach against known sample tiles (cluster0=bare_soil,
cluster1=vegetation, cluster2=open_water, cluster3=vegetation, per
e2b74973's labeled k=4 run).

Not part of the pipeline -- a one-off calibration/verification tool.
"""
import sys
from pathlib import Path

import cv2

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from classification.water_detection import land_cover_fractions, looks_like_water

TILE_DIR = Path(sys.argv[1])

KNOWN_LABELS = {
    "cluster0": "bare_soil",
    "cluster1": "vegetation",
    "cluster2": "open_water",
    "cluster3": "vegetation",
}

correct = 0
total = 0

print(f"{'label':<12} {'file':<28} {'water_frac':>10} {'veg_frac':>9} {'flagged':>10}")
for f in sorted(TILE_DIR.glob("cluster*_tile*.png")):
    cluster_key = f.name.split("_tile")[0]
    label = KNOWN_LABELS.get(cluster_key, "?")
    bgr = cv2.imread(str(f))
    if bgr is None:
        continue
    rgb = cv2.cvtColor(bgr, cv2.COLOR_BGR2RGB)  # match tiling.py's actual in-memory format
    water_frac, veg_frac = land_cover_fractions(rgb)
    flagged = looks_like_water(rgb)
    expected = label == "open_water"
    ok = flagged == expected
    correct += ok
    total += 1
    print(f"{label:<12} {f.name:<28} {water_frac:>10.3f} {veg_frac:>9.3f} {str(flagged):>10} {'' if ok else '  <-- WRONG'}")

print(f"\n{correct}/{total} correct")
