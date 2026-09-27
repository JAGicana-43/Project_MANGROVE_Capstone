<?php

namespace App\Services;

use App\Models\Orthomosaic;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Shells out to classification_service/scripts/generate_web_preview.py to
 * produce a small downscaled PNG of an orthomosaic, so the workspace map can
 * display it via a plain Leaflet image overlay instead of downloading and
 * client-side-parsing the full-resolution source GeoTIFF (verified: 131MB
 * on a real file in this project, every single page load).
 *
 * Deliberately synchronous/blocking (Process::run(), not the poll-loop
 * pattern ClassificationRunner uses) -- this takes ~15s on a real 131MB
 * file, nowhere near classification's multi-minute runtime, so it doesn't
 * need cancellation or live progress. Called from a queued job
 * (GenerateOrthomosaicPreviewJob), not inline in the upload request, purely
 * so a slow preview generation never delays the upload response the
 * researcher is actually waiting on.
 */
class OrthomosaicPreviewGenerator
{
    public function generate(Orthomosaic $orthomosaic): string
    {
        $sourcePath = storage_path('app/private/' . $orthomosaic->file_path);

        if (! File::exists($sourcePath)) {
            throw new \RuntimeException("Orthomosaic source file not found at {$sourcePath}");
        }

        $previewRelativePath = $this->previewRelativePath($orthomosaic);
        $previewAbsolutePath = storage_path('app/private/' . $previewRelativePath);

        $command = [
            config('classification.python_bin'),
            config('classification.preview_script_path'),
            $sourcePath,
            $previewAbsolutePath,
            '--max-dimension', (string) config('classification.preview_max_dimension'),
        ];

        $process = new Process($command, config('classification.working_directory'));
        // Generous but bounded -- the real file this was verified against
        // (131MB) took ~15s; this is headroom for a larger upload, not a
        // tuned figure.
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        if (! File::exists($previewAbsolutePath)) {
            throw new \RuntimeException('Preview generator exited successfully but wrote no output file.');
        }

        return $previewRelativePath;
    }

    public function previewRelativePath(Orthomosaic $orthomosaic): string
    {
        $directory = dirname($orthomosaic->file_path);

        return $directory . '/' . $orthomosaic->id . '-web-preview.png';
    }

    public function previewAbsolutePath(Orthomosaic $orthomosaic): string
    {
        return storage_path('app/private/' . $this->previewRelativePath($orthomosaic));
    }

    public function previewExists(Orthomosaic $orthomosaic): bool
    {
        return File::exists($this->previewAbsolutePath($orthomosaic));
    }
}
