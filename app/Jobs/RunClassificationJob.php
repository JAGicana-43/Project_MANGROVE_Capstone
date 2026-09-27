<?php

namespace App\Jobs;

use App\Exceptions\ClassificationCancelledException;
use App\Models\ClassificationRun;
use App\Models\SamplingGrid;
use App\Models\SuitabilitySurface;
use App\Services\ClassificationRunner;
use App\Services\SamplingPointGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Runs the actual Python classification subprocess off the request thread.
 * Dispatched onto the dedicated 'classification' queue, which only ever has
 * one worker process listening to it -- see the plan doc's reasoning: this
 * is a CPU/memory-heavy OS process, and unbounded concurrency here can
 * exhaust the site's PHP worker pool or the server's memory regardless of
 * how many web requests are involved.
 *
 * A single pass, fully automatic: the Python pipeline now auto-labels every
 * cluster from tile pixel content (see classification_service's
 * cluster_labeling.py), so there's no separate human-labeling round trip
 * left to wait on -- this job clusters, scores, and ingests the results
 * (SamplingGrid/SuitabilitySurface rows, then sampling points) in one
 * dispatch. $k stays a constructor param only so ClassificationController's
 * now-unused-by-the-frontend submitLabels()/getClusters() endpoints don't
 * need their signatures touched; passing it makes no behavioral difference
 * here anymore, since labeling no longer depends on it.
 */
class RunClassificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** No automatic retry -- a Python subprocess failure (bad file, bad
     * params) is deterministic, not transient. Retrying just delays telling
     * the user it failed. */
    public $tries = 1;

    public function __construct(
        public ClassificationRun $run,
        public ?int $k = null,
    ) {
    }

    public function handle(ClassificationRunner $runner, SamplingPointGenerator $pointGenerator): void
    {
        $outputDir = storage_path('app/private/classification-runs/' . $this->run->id);

        // Marks the moment a worker actually picked this up, distinct from
        // when it was dispatched -- status() reports "queued" for as long
        // as this stays null, so the frontend can tell "waiting in line"
        // apart from "actively running" instead of showing the same
        // message for both.
        $this->run->update(['started_at' => now()]);

        try {
            $summary = $runner->run(
                storage_path('app/private/' . $this->run->orthomosaic->file_path),
                $outputDir,
                $this->k,
                $this->run
            );

            $rows = json_decode(file_get_contents($outputDir . DIRECTORY_SEPARATOR . 'suitability_results.json'), true);

            foreach ($rows as $row) {
                SamplingGrid::create([
                    'run_id' => $this->run->id,
                    'grid_x' => $row['col'],
                    'grid_y' => $row['row'],
                    'dominant_cover' => $row['majority_label'],
                    'suitability_score' => $row['suitability_score'],
                    'cell_bounds' => [$row['geo_min_x'], $row['geo_min_y'], $row['geo_max_x'], $row['geo_max_y']],
                    'center_latitude' => $row['centroid_y'],
                    'center_longitude' => $row['centroid_x'],
                    'computed_at' => now(),
                ]);
            }

            $overlayRelativePath = 'classification-runs' . DIRECTORY_SEPARATOR . $this->run->id
                . DIRECTORY_SEPARATOR . 'suitability_overlay_web.png';

            SuitabilitySurface::create([
                'aoi_id' => $this->run->orthomosaic->aoi_id,
                'run_id' => $this->run->id,
                'output_file_path' => $overlayRelativePath,
                'file_format' => 'png',
                // Land-cover names, not suitability-tier names -- matches
                // the same labels used everywhere else in the app
                // (ReportDataAggregator's land_cover_breakdown, etc.), and
                // avoids implying a strict high/low binary the real 3-tier
                // scoring doesn't have.
                //
                // Colors computed directly from suitability_scoring.py's
                // own suitability_color() function at each land cover's
                // real DEFAULT_SUITABILITY_SCORES value (bare_soil=1.0,
                // vegetation=0.5) -- NOT the raw LOW_SCORE_COLOR/
                // HIGH_SCORE_COLOR endpoints. The old #C81E1E here was the
                // score=0.0 endpoint color, which nothing in this 3-tier
                // scheme actually scores -- vegetation (0.5) renders as
                // #735F2D (an interpolated olive-brown), a color that
                // wasn't represented in the legend at all. Re-derive these
                // if DEFAULT_SUITABILITY_SCORES or the color endpoints in
                // suitability_scoring.py ever change.
                'legend' => [
                    ['label' => 'Bare Soil', 'color' => '#1EA03C'],
                    ['label' => 'Vegetation', 'color' => '#735F2D'],
                    ['label' => 'Open Water', 'color' => '#6482A0'],
                    ['label' => 'Insufficient Data', 'color' => '#B4B4B4'],
                ],
                'generated_at' => now(),
            ]);

            // Created above unconditionally (cheap, and the common case is
            // "not cancelled") -- if the atomic claim below finds the run
            // was cancelled while this was running, those rows are now
            // orphaned and get cleaned up alongside the output directory,
            // rather than left attached to a run stuck at 'cancelled'.
            if ($this->finishIfStillProcessing([
                'status' => 'completed',
                'completed_at' => now(),
                'error_message' => null,
            ])) {
                // Automatic, same as the labeling itself now is -- generating
                // points used to be a separate manual step (a hidden button)
                // gated behind a human first submitting labels; now that
                // scoring itself is immediate and automatic, waiting on a
                // second manual click to see any points at all would just
                // reintroduce the same "why do I have to do something extra"
                // friction from a different angle.
                $pointGenerator->generate($this->run);
            } else {
                $this->run->samplingGrids()->delete();
                $this->run->suitabilitySurfaces()->delete();
                $this->discardOutput();
            }
        } catch (ClassificationCancelledException) {
            // Hard cancel: the subprocess was killed mid-flight by
            // ClassificationRunner the moment it noticed this run had been
            // cancelled. This isn't a failure -- the run is already
            // correctly 'cancelled' -- so it doesn't get logged as an error
            // or written over via finishIfStillProcessing(). Only cleanup
            // is needed for whatever partial output the killed process left
            // behind.
            $this->discardOutput();
        } catch (\Throwable $exception) {
            Log::error('Classification job failed.', [
                'classification_run_id' => $this->run->id,
                'exception' => $exception,
            ]);

            $this->finishIfStillProcessing([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }

    // A cancel request can land while this job is mid-flight (the Python
    // subprocess isn't interrupted -- see the plan doc's "soft cancel"
    // scope), so every status-changing write needs to be conditioned on
    // the run *still* being 'processing' at write time.
    //
    // This is deliberately a single atomic `UPDATE ... WHERE status =
    // 'processing'` rather than a separate read-then-write (e.g.
    // `if ($this->run->fresh()->status === 'cancelled') ...`) -- verified
    // directly that the read-then-write version has a real race: across
    // three live test runs (dispatch, wait for the job to genuinely start,
    // cancel mid-flight, watch what happens), the late completion write
    // still landed and overwrote a cancellation that had already committed
    // 20+ seconds earlier in 2 of 3 runs, despite an explicit fresh()
    // re-query before the write. Pushing the condition into the UPDATE
    // itself removes the separate read entirely, so there's no window
    // (however that earlier one was actually happening) for it to matter.
    //
    // Returns true if this write actually applied (run was still
    // 'processing'), false if it was skipped because the run had already
    // moved to some other status (in practice, 'cancelled').
    private function finishIfStillProcessing(array $attributes): bool
    {
        $updated = ClassificationRun::where('id', $this->run->id)
            ->where('status', 'processing')
            ->update($attributes);

        return $updated > 0;
    }

    // The Python subprocess isn't interrupted by a cancel (soft cancel --
    // see the plan doc), so by the time this runs it has already written a
    // complete output directory (tiles, model artifacts, results) that
    // nothing in the app will ever reference again. Deleting it here is
    // safe specifically because we only reach this after the subprocess
    // has finished writing -- doing this any earlier could race a still-
    // in-progress write.
    private function discardOutput(): void
    {
        $outputDir = storage_path('app/private/classification-runs/' . $this->run->id);

        Log::info('Classification job finished after being cancelled; discarding result.', [
            'classification_run_id' => $this->run->id,
            'output_dir' => $outputDir,
        ]);

        if (File::isDirectory($outputDir)) {
            File::deleteDirectory($outputDir);
        }
    }
}
