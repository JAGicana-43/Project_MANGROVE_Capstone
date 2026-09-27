<?php

namespace App\Services;

use App\Exceptions\ClassificationCancelledException;
use App\Models\ClassificationRun;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Stateless wrapper around classification_service/scripts/test_classification.py
 * -- shells out and hands back the parsed --summary-json output. Mirrors
 * WpmlKmzBuilder's shape (plain inputs in, a result derived from a written
 * file out) but for a subprocess instead of an in-process computation, since
 * the classification pipeline is Python, not PHP.
 *
 * Runs via Symfony Process's non-blocking start() + a poll loop, not the
 * simpler blocking run() -- this is what makes both hard-cancel (killing the
 * subprocess mid-flight) and real progress reporting possible.
 *
 * Progress is read from a small progress.json the pipeline writes to
 * output_dir during its dominant-cost feature-extraction step (see
 * pipeline.py's _write_progress()), not parsed from the subprocess's
 * stdout -- verified directly (dispatched a real job, polled the DB every
 * few seconds while it ran) that Symfony Process's output callback on this
 * Windows setup does not deliver stdout incrementally, only in one lump
 * when the process exits, which would make a stdout-based signal silently
 * useless. Reading a file the poll loop below already visits every
 * iteration sidesteps subprocess pipe buffering entirely.
 *
 * Dispatched onto the dedicated 'classification' queue (see
 * RunClassificationJob) -- this is a CPU/memory-heavy OS process, and
 * unbounded concurrency here can exhaust the site's PHP worker pool or the
 * server's memory regardless of how many web requests are involved.
 */
class ClassificationRunner
{
    private const POLL_INTERVAL_MICROSECONDS = 300_000; // 0.3s
    private const STOP_GRACE_PERIOD_SECONDS = 3;

    /**
     * @param  string  $geotiffPath  Absolute path to the source orthomosaic.
     * @param  string  $outputDir  Absolute path the pipeline should write all
     *                             its outputs (results, sample tiles, model
     *                             artifacts, overlay PNG) into.
     * @param  int|null  $k  Force this many clusters. Omit for the first
     *                       (auto k-sweep) pass; pass the chosen k back in
     *                       for the second pass once labels are written, so
     *                       the fit -- and therefore its model_id -- matches
     *                       exactly and the just-written label file applies.
     * @param  ClassificationRun|null  $run  When given, progress
     *                                       (processed_tiles/total_tiles) is
     *                                       updated live as the subprocess
     *                                       reports it, and the run's status
     *                                       is polled so a cancellation
     *                                       kills the subprocess instead of
     *                                       letting it finish unattended.
     *                                       Optional so this service stays
     *                                       usable without those concerns.
     * @return array<string, mixed> The parsed summary.json contents.
     *
     * @throws ClassificationCancelledException if $run was cancelled mid-run.
     * @throws \RuntimeException on a non-zero exit or unreadable summary file.
     */
    public function run(string $geotiffPath, string $outputDir, ?int $k = null, ?ClassificationRun $run = null): array
    {
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $summaryPath = $outputDir . DIRECTORY_SEPARATOR . 'summary.json';

        $command = [
            config('classification.python_bin'),
            config('classification.script_path'),
            $geotiffPath,
            '--output-dir', $outputDir,
            '--format', 'json',
            '--summary-json', $summaryPath,
            '--grid-cell-size', (string) config('classification.grid_cell_size'),
            '--grid-min-tiles', (string) config('classification.grid_min_tiles'),
        ];

        if ($k !== null) {
            $command[] = '--k';
            $command[] = (string) $k;
        }

        $process = new Process($command, config('classification.working_directory'));
        $process->setTimeout((int) config('classification.timeout_seconds', 300));

        if ($run !== null) {
            // Reset -- this column means "this run's" progress, not
            // whatever was left over from an earlier pass on the same row.
            $run->update(['processed_tiles' => 0]);
        }

        $progressPath = $outputDir . DIRECTORY_SEPARATOR . 'progress.json';
        $lastProcessed = 0;

        $process->start();

        while ($process->isRunning()) {
            if ($run !== null) {
                if ($run->fresh()->status === 'cancelled') {
                    $process->stop(self::STOP_GRACE_PERIOD_SECONDS);

                    throw new ClassificationCancelledException(
                        "Classification run {$run->id} was cancelled; subprocess killed."
                    );
                }

                $progress = $this->readProgressFile($progressPath);
                if ($progress !== null && $progress['processed'] !== $lastProcessed) {
                    $lastProcessed = $progress['processed'];
                    $run->update([
                        'processed_tiles' => $progress['processed'],
                        'total_tiles' => $progress['total'],
                    ]);
                }
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        }

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        if (! is_file($summaryPath)) {
            throw new \RuntimeException(
                'Classification process exited successfully but wrote no summary.json at ' . $summaryPath
            );
        }

        $summary = json_decode(file_get_contents($summaryPath), true);

        if (! is_array($summary)) {
            throw new \RuntimeException('Classification summary.json was not valid JSON.');
        }

        return $summary;
    }

    /**
     * @return array{processed: int, total: int}|null
     */
    private function readProgressFile(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        // Python writes this atomically (temp file + rename -- see
        // pipeline.py's _write_progress()), so a torn/partial read here
        // shouldn't normally happen -- still guarded since this file gets
        // read many times a second by a process that isn't the one writing
        // it, and a transient unreadable moment shouldn't blow up the run.
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $data = json_decode($contents, true);
        if (! is_array($data) || ! isset($data['processed'], $data['total'])) {
            return null;
        }

        return ['processed' => (int) $data['processed'], 'total' => (int) $data['total']];
    }
}
