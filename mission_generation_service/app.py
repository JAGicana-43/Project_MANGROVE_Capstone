"""
Minimal Flask wrapper around the pipeline.

NOT wired into the Laravel app yet — this is the standalone module + test
harness deliverable. Wiring (Laravel calling this over HTTP, auth, storing
the output KMZ, an audit-trail DB row) is a deliberately separate next step,
same as the "what I still have to verify myself" scope boundary in the
original request.
"""
from __future__ import annotations

import os
import tempfile

from flask import Flask, jsonify, request, send_file

from .drone_profiles import UnknownDroneModelError
from .pipeline import generate_mission
from .validation import AOIValidationError
from .waypoint_generator import WaypointGenerationError

app = Flask(__name__)


@app.post("/generate")
def generate():
    payload = request.get_json(force=True, silent=True) or {}

    aoi_polygon = payload.get("aoi_polygon")
    drone_key = payload.get("drone_key")
    # overlap_percent / finish_action are optional — omitting them falls back
    # to generate_mission()'s defaults (the validated real-world baseline),
    # rather than being required inputs. The AOI polygon + drone are the only
    # things that must be supplied per call.
    overlap_percent = payload.get("overlap_percent")
    finish_action = payload.get("finish_action") or "goHome"

    if not isinstance(aoi_polygon, list) or len(aoi_polygon) < 3:
        return jsonify({"error": "aoi_polygon must be a list of at least 3 [lat, lng] pairs."}), 400
    if not drone_key:
        return jsonify({"error": "drone_key is required."}), 400

    polygon_latlng = [(float(p[0]), float(p[1])) for p in aoi_polygon]

    fd, output_path = tempfile.mkstemp(suffix=".kmz")
    os.close(fd)

    try:
        kwargs = {} if overlap_percent is None else {"overlap_percent": float(overlap_percent)}
        summary = generate_mission(polygon_latlng, drone_key, output_path=output_path, finish_action=finish_action, **kwargs)
    except (AOIValidationError, UnknownDroneModelError, WaypointGenerationError) as exc:
        os.remove(output_path)
        return jsonify({"error": str(exc)}), 422

    summary.pop("waypoints")  # full waypoint list is in the KMZ; keep the JSON summary small
    return jsonify(summary | {"download_available": True, "output_path": output_path})


@app.get("/generate/download")
def download():
    path = request.args.get("path")
    if not path or not os.path.isfile(path):
        return jsonify({"error": "No such generated file."}), 404
    return send_file(path, mimetype="application/vnd.google-earth.kmz", as_attachment=True)


if __name__ == "__main__":
    app.run(debug=True)
