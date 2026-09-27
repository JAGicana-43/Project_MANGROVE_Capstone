<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Report;
use App\Models\SamplingPoint;
use App\Services\ReportDataAggregator;
use App\Services\SimpleChartRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Generates a single, real report type ("full_survey"): everything a
 * researcher would want to hand off or file for a project's current state
 * -- suitability overlay + breakdown, then every sampling point with
 * whatever soil reading exists for it, fused with soil-reading-derived
 * charts (suitability breakdown, parameter ranges) via
 * ReportDataAggregator/SimpleChartRenderer -- the same aggregation the
 * live web Reports dashboard uses, so the PDF and the dashboard never
 * disagree about these numbers. Soil reading data comes from the same
 * soil_readings rows regardless of source (mobile app is the only writer
 * now -- see MobileSyncController) -- this report doesn't need to change
 * when that data's origin changes, since it already reads whatever is in
 * that table.
 */
class ReportController extends Controller
{
    public function generate(Request $request, Project $project, ReportDataAggregator $aggregator, SimpleChartRenderer $charts): JsonResponse
    {
        $user = Auth::user();
        abort_unless(
            $project->created_by === $user->id || $project->members()->where('user_id', $user->id)->exists(),
            403
        );

        $aoi = $project->areasOfInterest()->where('status', 'active')->latest()->first();
        abort_unless($aoi, 422, 'No area of interest defined yet for this project.');

        $mission = $aoi->droneMissions()->latest()->first();
        abort_unless($mission, 422, 'No drone mission has been planned for this area yet.');

        $orthomosaic = $mission->orthomosaics()->latest()->first();
        abort_unless($orthomosaic, 422, 'No orthomosaic has been uploaded for this area yet.');

        $run = $orthomosaic->classificationRuns()->latest()->first();
        abort_unless($run && $run->status === 'completed', 422, 'Classification must finish before a report can be generated.');

        $surface = $run->suitabilitySurfaces()->latest()->first();
        $grids = $run->samplingGrids;

        $summary = $aggregator->summarize($run);

        $points = SamplingPoint::whereIn('grid_id', $grids->pluck('id'))
            ->with(['soilReading.collector'])
            ->orderBy('priority_rank')
            ->get();

        // Embedded as a data URI rather than a filesystem path -- dompdf's
        // handling of local paths depends on chroot/remote-enabled config
        // that varies by environment; a base64 data URI always works the
        // same way regardless of that config.
        $overlayPath = storage_path('app/private/classification-runs/' . $run->id . '/suitability_overlay_web.png');
        $overlayDataUri = File::exists($overlayPath)
            ? $this->downscaledImageDataUri($overlayPath)
            : null;

        // Suitability breakdown as a bar chart -- ordered best-to-worst,
        // "not yet rated" appended so a technician who collected a reading
        // but skipped the rating field is still visible on the chart.
        $suitabilityColors = ['suitable' => [45, 106, 79], 'marginal' => [183, 121, 31], 'unsuitable' => [180, 35, 24]];
        $suitabilityChartItems = $summary['suitability_breakdown']
            ->map(fn ($row) => ['label' => $row['label'], 'value' => $row['count'], 'color' => $suitabilityColors[$row['rating']]])
            ->values()
            ->all();
        if ($summary['not_rated_count'] > 0) {
            $suitabilityChartItems[] = ['label' => 'Not rated', 'value' => $summary['not_rated_count'], 'color' => [173, 181, 189]];
        }
        $suitabilityChartUri = $summary['total_readings'] > 0 ? $charts->horizontalBarChart($suitabilityChartItems) : null;
        $parameterChartUri = $charts->rangeChart($summary['parameter_stats']->all());

        $generatedAt = now();

        $pdf = Pdf::loadView('reports.full-survey', [
            'project' => $project,
            'aoi' => $aoi,
            'run' => $run,
            'breakdown' => $summary['land_cover_breakdown'],
            'totalCells' => $summary['total_cells'],
            'points' => $points,
            'overlayDataUri' => $overlayDataUri,
            'suitabilityChartUri' => $suitabilityChartUri,
            'parameterChartUri' => $parameterChartUri,
            'flight' => $summary['flight'],
            'speciesBreakdown' => $summary['species_breakdown'],
            'legend' => $surface?->legend ?? [],
            'generatedBy' => $user,
            'generatedAt' => $generatedAt,
        ])->setPaper('a4');

        $filename = 'full-survey-' . now()->format('Ymd-His') . '.pdf';
        $relativePath = 'reports/' . $project->id . '/' . $filename;
        File::ensureDirectoryExists(storage_path('app/private/reports/' . $project->id));
        File::put(storage_path('app/private/' . $relativePath), $pdf->output());

        $report = Report::create([
            'project_id' => $project->id,
            'generated_by' => $user->id,
            'title' => $project->name . ' -- Full Survey Report',
            'report_type' => 'full_survey',
            'file_path' => $relativePath,
            'file_format' => 'pdf',
            'generated_at' => $generatedAt,
        ]);

        return response()->json([
            'message' => 'Report generated.',
            'report' => [
                'id' => $report->id,
                'title' => $report->title,
                'generated_at' => $report->generated_at->format('M d, Y g:i A'),
                'download_url' => route('projects.reports.download', [$project, $report]),
            ],
        ]);
    }

    // The overlay PNG is saved at the orthomosaic's full pixel resolution
    // (verified directly: 7389x5111 on the real test file) for the Leaflet
    // map view, where that detail is genuinely useful when zoomed in. A PDF
    // report has no such use for it -- embedding the raw file blew dompdf's
    // memory limit outright (confirmed: a real generate() call fatal-errored
    // with "Allowed memory size... exhausted" in Cpdf.php before this fix).
    // 1400px wide is plenty for a page-width image in a document.
    private function downscaledImageDataUri(string $path, int $maxWidth = 1400): ?string
    {
        $source = @imagecreatefrompng($path);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width <= $maxWidth) {
            $resized = $source;
        } else {
            $targetHeight = (int) round($height * ($maxWidth / $width));
            $resized = imagecreatetruecolor($maxWidth, $targetHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $maxWidth, $targetHeight, $width, $height);
        }

        ob_start();
        imagepng($resized);
        $pngData = ob_get_clean();

        imagedestroy($source);
        if ($resized !== $source) {
            imagedestroy($resized);
        }

        return 'data:image/png;base64,' . base64_encode($pngData);
    }

    public function download(Request $request, Project $project, Report $report): BinaryFileResponse
    {
        $user = Auth::user();
        abort_unless(
            $project->created_by === $user->id || $project->members()->where('user_id', $user->id)->exists(),
            403
        );
        abort_unless($report->project_id === $project->id, 404);

        $path = storage_path('app/private/' . $report->file_path);
        abort_unless(File::exists($path), 404, 'This report file is no longer available.');

        $report->incrementDownload();

        return response()->download($path, Str::slug($report->title) . '.' . $report->file_format);
    }
}
