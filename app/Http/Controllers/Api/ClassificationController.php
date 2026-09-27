<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunClassificationJob;
use App\Models\ClassificationRun;
use App\Models\Orthomosaic;
use App\Models\SamplingGrid;
use App\Models\SamplingPoint;
use App\Services\SamplingPointGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Wires classification_service's Python pipeline into the workspace: run
 * analysis on an uploaded orthomosaic, let a researcher label whatever
 * clusters come back (every orthomosaic gets its own fresh, independent
 * fit -- see the plan doc's "known limitation" note), then ingest the
 * now-labeled results and generate sampling points from them.
 *
 * The actual subprocess work (RunClassificationJob) runs on a dedicated,
 * single-worker 'classification' queue, not inline in the request -- a
 * classification run is a real CPU/memory-heavy OS process, and running it
 * synchronously in a web request would tie up a PHP worker for its full
 * duration and let concurrent users exhaust the server. Every method here
 * either dispatches that job and returns immediately, or reads back
 * whatever state the job has written so far (status()).
 *
 * Two-pass labeling flow (mirrors how classification_service's own
 * model_registry.py already works over the CLI, just automated):
 *   1. runClassification: dispatches the job with auto k-sweep, no
 *      ingestion yet (labels are all UNLABELED at this point, scoring
 *      would be meaningless). Frontend polls status() until pending_labels.
 *   2. getClusters: shows sample tiles per cluster for review.
 *   3. submitLabels: writes the label file, dispatches the job again with
 *      the same k forced (same model_id, so the label file applies).
 *      Frontend polls status() until completed.
 */
class ClassificationController extends Controller
{
    private const VALID_LABELS = ['bare_soil', 'vegetation', 'open_water', 'unclassified'];

    public function runClassification(Request $request, Orthomosaic $orthomosaic): JsonResponse
    {
        $this->authorizeOrthomosaic($request, $orthomosaic);

        // Nothing in the UI should be able to trigger this while a run is
        // already active (the button disables itself, and the page reloads
        // to real server state instead of a stale bfcache snapshot on
        // back/forward -- see partials/page-transition.blade.php), but nothing
        // enforced it server-side either -- two tabs, or any other path that
        // reaches this endpoint twice, could dispatch two Python subprocesses
        // against the same orthomosaic. Verified this actually happens in
        // practice (ended up with two simultaneous pending_labels runs on one
        // orthomosaic during testing), not just a theoretical race.
        abort_if(
            $orthomosaic->classificationRuns()->whereIn('status', ['processing', 'pending_labels'])->exists(),
            422,
            'This orthomosaic already has an analysis in progress.'
        );

        // A fresh analysis supersedes any prior one for this orthomosaic --
        // by this point every existing run is guaranteed terminal (completed/
        // failed/cancelled) thanks to the guard above, so nothing here is
        // still being worked on. Deleting the run row cascades to its
        // SamplingGrid/SamplingPoint/SoilReading/SuitabilitySurface rows via
        // FK constraints (all onDelete('cascade') -- see their migrations),
        // so only the output directory on disk needs deleting separately.
        // Without this, every re-analysis (redo labeling, try again) left
        // its full output behind forever -- sample tiles, fitted model
        // files, and a feature cache that hit 87MB on just one test run --
        // with nothing ever cleaning it up.
        foreach ($orthomosaic->classificationRuns as $priorRun) {
            File::deleteDirectory($this->runOutputDir($priorRun));
            $priorRun->delete();
        }

        // started_at is deliberately left null here -- it's set inside
        // RunClassificationJob::handle() the moment a worker actually picks
        // this up, not at dispatch time. status() uses that gap (status ==
        // 'processing' but started_at still null) to tell the frontend
        // "queued, waiting for a worker" apart from "actively running" --
        // otherwise both look identical to a user, especially now that jobs
        // can genuinely queue behind each other (see the plan doc).
        $run = ClassificationRun::create([
            'orthomosaic_id' => $orthomosaic->id,
            'status' => 'processing',
        ]);

        RunClassificationJob::dispatch($run)->onQueue('classification');

        return response()->json([
            'message' => 'Analysis queued.',
            'classification_run_id' => $run->id,
            'status' => 'processing',
            'status_url' => route('classification.status', $run),
            'cancel_url' => route('classification.cancel', $run),
        ]);
    }

    public function cancel(Request $request, ClassificationRun $classificationRun): JsonResponse
    {
        $this->authorizeRun($request, $classificationRun);

        abort_unless($classificationRun->status === 'processing', 422, 'Nothing to cancel.');

        // The running job isn't interrupted -- it finishes naturally and
        // discards its result once it sees this status (see
        // RunClassificationJob::finishUnlessCancelled()). This just stops
        // the frontend from waiting on it and stops that later result from
        // overwriting the user's decision.
        $classificationRun->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json(['message' => 'Analysis cancelled.']);
    }

    public function status(Request $request, ClassificationRun $classificationRun): JsonResponse
    {
        $this->authorizeRun($request, $classificationRun);

        $status = $classificationRun->status;

        if (in_array($status, ['processing', 'failed', 'cancelled'], true)) {
            return response()->json([
                'status' => $status,
                // Only meaningful while status is 'processing': true means
                // still sitting in the queue, no worker has picked it up
                // yet (started_at is only set inside the job's handle()).
                'queued' => $status === 'processing' && $classificationRun->started_at === null,
                'error_message' => $classificationRun->error_message,
                'cancel_url' => $status === 'processing' ? route('classification.cancel', $classificationRun) : null,
                // Only meaningful once a worker has actually started (see
                // 'queued' above) and only granular during the first pass --
                // the second pass reuses cached features and skips the
                // per-tile step this is derived from, so it stays flat.
                'progress_percent' => $classificationRun->progressPercent(),
            ]);
        }

        $outputDir = $this->runOutputDir($classificationRun);
        $summary = $this->readSummary($outputDir);
        $quality = [
            'chosen_k' => $summary['chosen_k'],
            'silhouette' => $summary['silhouette_score'],
        ];

        if ($status === 'pending_labels') {
            return response()->json([
                'status' => $status,
                'quality' => $quality,
                'clusters_url' => route('classification.clusters', $classificationRun),
                'labels_url' => route('classification.labels', $classificationRun),
            ]);
        }

        // completed
        $surface = $classificationRun->suitabilitySurfaces()->latest()->first();
        // Generated automatically by RunClassificationJob the moment this
        // run completed -- included directly here so the live poll right
        // after clicking Analyze can render them immediately, the same as
        // a page reload already does via $existingClassificationConfig.
        $points = SamplingPoint::whereIn('grid_id', $classificationRun->samplingGrids()->pluck('id'))
            ->orderBy('priority_rank')
            ->get(['id', 'latitude', 'longitude', 'priority_rank']);

        return response()->json([
            'status' => $status,
            'overlay_url' => route('classification.overlay', $classificationRun),
            'legend' => $surface?->legend,
            'score_summary' => $summary['score_summary'],
            'quality' => $quality,
            'sampling_points_url' => route('classification.sampling-points', $classificationRun),
            'grid_cells_url' => route('classification.grid-cells', $classificationRun),
            'points' => $points,
        ]);
    }

    public function getClusters(Request $request, ClassificationRun $classificationRun): JsonResponse
    {
        $this->authorizeRun($request, $classificationRun);

        $outputDir = $this->runOutputDir($classificationRun);
        $summary = $this->readSummary($outputDir);

        $sampleTilesDir = $outputDir . DIRECTORY_SEPARATOR . 'sample_tiles';
        $filesByCluster = [];

        if (is_dir($sampleTilesDir)) {
            foreach (scandir($sampleTilesDir) as $filename) {
                if (preg_match('/^cluster(\d+)_tile\d+\.png$/', $filename, $matches)) {
                    $filesByCluster[(int) $matches[1]][] = $filename;
                }
            }
        }

        // Pre-fills the labeling form from whatever was assigned the last
        // time this same orthomosaic was labeled -- but only if this run's
        // model_id matches the one those labels were actually submitted
        // against. cluster_id only keeps meaning the same thing run to run
        // when the fit is genuinely identical (same file, same tile size/
        // nodata threshold/feature set/extractor/k) -- model_id already
        // fingerprints exactly that. Without this check, changing anything
        // about the pipeline (verified directly: swapping the feature
        // extractor) silently carries the *old* index-to-label mapping
        // onto a *new* clustering where cluster 0 means something
        // completely different, producing confidently-wrong defaults that
        // looked fine enough to submit without noticing.
        $orthomosaic = $classificationRun->orthomosaic;
        $defaultLabels = $orthomosaic->last_cluster_labels_model_id === $summary['model_id']
            ? ($orthomosaic->last_cluster_labels ?? [])
            : [];

        $clusters = [];
        foreach ($summary['tiles_per_cluster'] as $clusterId => $tileCount) {
            $clusterId = (int) $clusterId;
            $clusters[] = [
                'cluster_id' => $clusterId,
                'tile_count' => $tileCount,
                'sample_tile_urls' => collect($filesByCluster[$clusterId] ?? [])
                    ->map(fn (string $filename) => route('classification.sample-tile', [$classificationRun, $filename]))
                    ->values(),
                'default_label' => $defaultLabels[$clusterId] ?? null,
            ];
        }

        return response()->json(['clusters' => $clusters, 'valid_labels' => self::VALID_LABELS]);
    }

    public function submitLabels(Request $request, ClassificationRun $classificationRun): JsonResponse
    {
        $this->authorizeRun($request, $classificationRun);

        // pending_labels is the only state labels can be validly submitted
        // from -- completed means they already were; processing means a
        // second pass is already running from an earlier submission (double-
        // submitting here would dispatch a second Python subprocess writing
        // to the same output directory concurrently, corrupting whichever
        // file both happen to write at once); cancelled/failed have no
        // reliable first-pass output to score. Was previously only checking
        // != 'completed', which let the processing case through.
        if ($classificationRun->status !== 'pending_labels') {
            $message = match ($classificationRun->status) {
                'completed' => 'Labels were already submitted for this run.',
                'processing' => 'This run is already being scored.',
                default => 'This run is not currently awaiting labels.',
            };

            return response()->json(['message' => $message], 422);
        }

        $validated = $request->validate([
            'labels' => ['required', 'array', 'min:1'],
            'labels.*' => ['required', 'string', 'in:' . implode(',', self::VALID_LABELS)],
        ]);

        $outputDir = $this->runOutputDir($classificationRun);
        $firstPassSummary = $this->readSummary($outputDir);

        $labelsPath = $outputDir . DIRECTORY_SEPARATOR . 'model_artifacts' . DIRECTORY_SEPARATOR
            . 'model_' . $firstPassSummary['model_id'] . '.labels.json';

        // JSON_FORCE_OBJECT matters here: cluster ids are always sequential
        // from 0, so $validated['labels'] (keyed "0","1","2"...) is
        // indistinguishable from a plain list to json_encode -- without
        // this flag it would serialize as a JSON array, and Python's
        // model_registry.load_or_init_labels() (which calls
        // saved_labels.get(str(cluster_id), ...)) would crash on it since a
        // list has no .get(). Verified directly: this crashed a real
        // second-pass run with AttributeError before this fix.
        File::put($labelsPath, json_encode([
            'model_id' => $firstPassSummary['model_id'],
            'generated_at' => now()->toIso8601String(),
            'instructions' => 'Written by the workspace UI, not hand-edited.',
            'labels' => $validated['labels'],
        ], JSON_PRETTY_PRINT | JSON_FORCE_OBJECT));

        // Remembered so a later re-run of this same orthomosaic can pre-fill
        // the labeling form instead of starting blank -- see getClusters(),
        // which only trusts this when a later run's model_id matches.
        $classificationRun->orthomosaic->update([
            'last_cluster_labels' => $validated['labels'],
            'last_cluster_labels_model_id' => $firstPassSummary['model_id'],
        ]);

        // started_at reset to null for the same reason as runClassification()
        // -- this second pass is a fresh dispatch that can queue behind
        // other work just like the first one did.
        $classificationRun->update(['status' => 'processing', 'started_at' => null]);

        RunClassificationJob::dispatch($classificationRun, (int) $firstPassSummary['chosen_k'])
            ->onQueue('classification');

        return response()->json([
            'message' => 'Labels submitted, scoring in progress.',
            'status' => 'processing',
            'status_url' => route('classification.status', $classificationRun),
            'cancel_url' => route('classification.cancel', $classificationRun),
        ]);
    }

    public function generateSamplingPoints(Request $request, ClassificationRun $classificationRun, SamplingPointGenerator $generator): JsonResponse
    {
        $this->authorizeRun($request, $classificationRun);

        abort_unless($classificationRun->status === 'completed', 422, 'Classification must finish scoring before generating sampling points.');

        // RunClassificationJob already generates points automatically the
        // moment a run completes -- this endpoint is a leftover manual
        // trigger, kept only so it isn't a breaking API change. Guarding
        // against a second call is what actually matters now: without it,
        // hitting this after the automatic generation already ran would
        // create a full second, duplicate set of points for the same run.
        abort_if(
            SamplingPoint::whereIn('grid_id', $classificationRun->samplingGrids()->pluck('id'))->exists(),
            422,
            'Sampling points were already generated for this run.'
        );

        $points = $generator->generate($classificationRun);

        return response()->json([
            'message' => $points->isEmpty()
                ? 'No cells scored suitable enough to generate a sampling point.'
                : $points->count() . ' sampling point(s) generated.',
            'points' => $points->map(fn ($point) => [
                'id' => $point->id,
                'latitude' => $point->latitude,
                'longitude' => $point->longitude,
                'priority_rank' => $point->priority_rank,
            ]),
            'point_count' => $points->count(),
        ]);
    }

    // Mirrors classification_service/classification/suitability_scoring.py's
    // DEFAULT_SUITABILITY_SCORES -- kept in sync manually since this side
    // needs to recompute a score immediately on a manual override without
    // shelling out to Python for one cell. If that file's scoring config
    // ever changes, this must change with it.
    private const SUITABILITY_SCORES = [
        'bare_soil' => 1.0,
        'vegetation' => 0.5,
        'open_water' => null,
    ];

    public function getGridCells(Request $request, ClassificationRun $classificationRun): JsonResponse
    {
        $this->authorizeRun($request, $classificationRun);

        $cells = $classificationRun->samplingGrids()->get([
            'id', 'dominant_cover', 'suitability_score', 'manually_overridden', 'cell_bounds',
        ])->map(function (SamplingGrid $cell) use ($classificationRun) {
            [$minLng, $minLat, $maxLng, $maxLat] = $cell->cell_bounds;

            return [
                'id' => $cell->id,
                'dominant_cover' => $cell->dominant_cover,
                'suitability_score' => $cell->suitability_score,
                'manually_overridden' => $cell->manually_overridden,
                'bounds' => [$minLat, $minLng, $maxLat, $maxLng],
                'update_url' => route('classification.grid-cells.update', [$classificationRun, $cell]),
            ];
        });

        return response()->json(['cells' => $cells, 'valid_labels' => self::VALID_LABELS]);
    }

    public function updateGridCell(Request $request, ClassificationRun $classificationRun, SamplingGrid $gridCell): JsonResponse
    {
        $this->authorizeRun($request, $classificationRun);

        abort_unless($gridCell->run_id === $classificationRun->id, 404);

        $validated = $request->validate([
            'dominant_cover' => ['required', 'string', 'in:' . implode(',', self::VALID_LABELS)],
        ]);

        $label = $validated['dominant_cover'];
        $gridCell->update([
            'dominant_cover' => $label,
            'suitability_score' => self::SUITABILITY_SCORES[$label] ?? null,
            'manually_overridden' => true,
        ]);

        return response()->json([
            'message' => 'Cell updated.',
            'cell' => [
                'id' => $gridCell->id,
                'dominant_cover' => $gridCell->dominant_cover,
                'suitability_score' => $gridCell->suitability_score,
                'manually_overridden' => true,
            ],
        ]);
    }

    public function serveOverlay(Request $request, ClassificationRun $classificationRun): BinaryFileResponse
    {
        $this->authorizeRun($request, $classificationRun);

        $path = $this->runOutputDir($classificationRun) . DIRECTORY_SEPARATOR . 'suitability_overlay_web.png';
        abort_unless(File::exists($path), 404);

        return response()->file($path, ['Content-Type' => 'image/png']);
    }

    public function serveSampleTile(Request $request, ClassificationRun $classificationRun, string $filename): BinaryFileResponse
    {
        $this->authorizeRun($request, $classificationRun);

        abort_unless((bool) preg_match('/^cluster\d+_tile\d+\.png$/', $filename), 404);

        $path = $this->runOutputDir($classificationRun) . DIRECTORY_SEPARATOR . 'sample_tiles' . DIRECTORY_SEPARATOR . $filename;
        abort_unless(File::exists($path), 404);

        return response()->file($path, ['Content-Type' => 'image/png']);
    }

    private function runOutputDir(ClassificationRun $run): string
    {
        return storage_path('app/private/classification-runs/' . $run->id);
    }

    private function readSummary(string $outputDir): array
    {
        $path = $outputDir . DIRECTORY_SEPARATOR . 'summary.json';
        abort_unless(File::exists($path), 404, 'No classification results found for this run.');

        return json_decode(file_get_contents($path), true);
    }

    private function authorizeOrthomosaic(Request $request, Orthomosaic $orthomosaic): void
    {
        $user = Auth::user()->load('role');
        $project = $orthomosaic->areaOfInterest->project;

        abort_unless(
            $project->created_by === $user->id || $project->members()->where('user_id', $user->id)->exists(),
            403
        );
    }

    private function authorizeRun(Request $request, ClassificationRun $run): void
    {
        $this->authorizeOrthomosaic($request, $run->orthomosaic);
    }
}
