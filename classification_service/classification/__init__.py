"""
Tile-classification pipeline (GeoTIFF -> tiles -> ResNet18+VARI/ExG/GLI
features -> K-Means clusters -> auto-labeled grid -> suitability scoring).
Invoked from the real Laravel app via ClassificationRunner (Symfony
Process shelling out to this package's pipeline.run_classification()) --
no longer a standalone prototype. scripts/test_classification.py remains
useful as a manual CLI entry point for local testing/calibration work
outside the web app.
"""
