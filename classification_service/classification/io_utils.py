"""Shared CSV/JSON row writer used across result exports."""
from __future__ import annotations

import csv
import json
from pathlib import Path


def write_rows(rows: list[dict], output_path: Path) -> None:
    if not rows:
        output_path.write_text("[]" if output_path.suffix.lower() == ".json" else "")
        return
    if output_path.suffix.lower() == ".json":
        output_path.write_text(json.dumps(rows, indent=2))
    else:
        with output_path.open("w", newline="") as f:
            writer = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
            writer.writeheader()
            writer.writerows(rows)
