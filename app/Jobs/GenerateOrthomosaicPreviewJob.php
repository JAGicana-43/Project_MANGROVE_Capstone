<?php

namespace App\Jobs;

use App\Models\Orthomosaic;
use App\Services\OrthomosaicPreviewGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs off the request thread so a slow preview generation (~15s on a real
 * 131MB file) never delays the upload response -- dispatched right after
 * upload, onto the same supervised 'classification' queue/worker
 * (OrthomosaicPreviewGenerator's subprocess is short compared to a real
 * classification run, so it doesn't need its own dedicated worker).
 *
 * Best-effort: if this fails, the workspace map simply falls back to the
 * original full-file client-side rendering (see map-workspace.js) rather
 * than being blocked on this succeeding -- an orthomosaic is still fully
 * usable without its preview, just slower to display.
 */
class GenerateOrthomosaicPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public function __construct(public Orthomosaic $orthomosaic)
    {
    }

    public function handle(OrthomosaicPreviewGenerator $generator): void
    {
        try {
            $generator->generate($this->orthomosaic);
        } catch (\Throwable $exception) {
            Log::warning('Orthomosaic preview generation failed; map will fall back to full-file rendering.', [
                'orthomosaic_id' => $this->orthomosaic->id,
                'exception' => $exception,
            ]);
        }
    }
}
