<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\GisExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Bundles the current classification run's results as GeoJSON for
 * QGIS/ArcGIS -- three files (boundary, grid, points) zipped together so
 * each imports as its own correctly-symbolized layer, following the same
 * AOI -> mission -> orthomosaic -> completed run resolution as
 * ReportController::generate().
 */
class GisExportController extends Controller
{
    public function export(Request $request, Project $project, GisExportService $service): BinaryFileResponse
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
        abort_unless($run && $run->status === 'completed', 422, 'Classification must finish before a GIS export can be generated.');

        $exportDir = storage_path('app/private/gis-exports/' . Str::uuid());
        File::ensureDirectoryExists($exportDir);

        File::put($exportDir . '/aoi-boundary.geojson', json_encode($service->boundaryFeatureCollection($aoi), JSON_PRETTY_PRINT));
        File::put($exportDir . '/classification-grid.geojson', json_encode($service->gridFeatureCollection($run), JSON_PRETTY_PRINT));
        File::put($exportDir . '/sampling-points.geojson', json_encode($service->pointsFeatureCollection($run), JSON_PRETTY_PRINT));

        $zipPath = $exportDir . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($exportDir . '/aoi-boundary.geojson', 'aoi-boundary.geojson');
        $zip->addFile($exportDir . '/classification-grid.geojson', 'classification-grid.geojson');
        $zip->addFile($exportDir . '/sampling-points.geojson', 'sampling-points.geojson');
        $zip->close();

        File::deleteDirectory($exportDir);

        $filename = Str::slug($project->name) . '-gis-export.zip';

        return response()->download($zipPath, $filename)->deleteFileAfterSend(true);
    }
}
