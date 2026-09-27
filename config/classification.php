<?php

// config/classification.php
//
// Points Laravel at the standalone classification_service/ Python pipeline
// (GeoTIFF tiling -> clustering -> suitability scoring). That service has
// its own dedicated virtualenv (rasterio/torch/opencv wheels aren't
// available for every Python version), so the interpreter path matters --
// it must be this venv's python.exe, not whatever `python` resolves to on
// PATH.

return [

    // Absolute path to the classification_service venv's python executable.
    // Override via CLASSIFICATION_PYTHON_BIN in .env for a different machine.
    'python_bin' => env(
        'CLASSIFICATION_PYTHON_BIN',
        base_path('classification_service/.venv-classify/Scripts/python.exe')
    ),

    // scripts/test_classification.py resolves its own imports relative to
    // its own file location regardless of cwd, but this is still the
    // directory the process should run from (matches how it's documented
    // and run manually in PIPELINE_STATUS.md).
    'working_directory' => base_path('classification_service'),

    'script_path' => base_path('classification_service/scripts/test_classification.py'),

    // Generates the downscaled web-preview PNG shown on the workspace map
    // (see OrthomosaicPreviewGenerator) -- separate from the classification
    // pipeline above; this never touches the full-resolution source file
    // used for actual analysis, it only produces a smaller image for
    // on-screen display.
    'preview_script_path' => base_path('classification_service/scripts/generate_web_preview.py'),
    'preview_max_dimension' => env('CLASSIFICATION_PREVIEW_MAX_DIMENSION', 2000),

    // How long a single classification subprocess call may run before
    // Laravel gives up on it. AOIs are capped at 4ha (AoiValidator), so this
    // is generous headroom, not a tuned figure.
    'timeout_seconds' => env('CLASSIFICATION_TIMEOUT_SECONDS', 300),

    // Sampling grid cell size in pixels, passed as --grid-cell-size. The
    // pipeline's own CLI default (1120px = 5x5 tiles) was tuned for the
    // small CLI test file this pipeline was originally validated against
    // -- on a real drone orthomosaic (multiple thousand px per side) it
    // produces only a handful of giant grid cells covering the whole
    // image, losing almost all spatial detail (verified directly: a
    // 7389x5111px image produced ~30 total cells and a suitability overlay
    // that didn't track real vegetation/soil/water boundaries at all).
    // 224px = one tile per cell removes the grid-aggregation majority-vote
    // step entirely, so the suitability map has the same resolution as the
    // underlying tile classification -- confirmed to produce a visibly
    // coherent result on the same test image.
    'grid_cell_size' => env('CLASSIFICATION_GRID_CELL_SIZE', 224),

    // Minimum valid tiles per cell before it's flagged INSUFFICIENT_DATA
    // rather than scored. At one tile per cell (the default above) this is
    // moot -- a cell either has its one tile or doesn't exist -- but stays
    // configurable for anyone who widens grid_cell_size back up.
    'grid_min_tiles' => env('CLASSIFICATION_GRID_MIN_TILES', 1),

    // Max real-world distance (meters) between two SamplingGrid cell
    // centroids from independent ClassificationRuns for
    // SurveyComparisonService to treat them as "the same cell" across
    // timelines. Two orthomosaics of the same site come from independent
    // flights with unrelated tiling origins, so grid_x/grid_y indices
    // aren't comparable -- matching is by nearest real-world centroid
    // instead, and this threshold rejects edge cells that don't have a
    // real counterpart in the other run rather than force-matching them.
    'comparison_match_max_distance_meters' => env('CLASSIFICATION_COMPARISON_MATCH_MAX_DISTANCE_METERS', 5.0),
];
