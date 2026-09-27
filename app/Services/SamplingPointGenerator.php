<?php

namespace App\Services;

use App\Models\ClassificationRun;
use App\Models\SamplingGrid;
use App\Models\SamplingPoint;
use App\Services\Geo\PolygonGeometry;

/**
 * Turns a classification run's scored SamplingGrid cells into actual
 * SamplingPoint rows a field technician can be sent to.
 *
 * Only bare_soil cells at the top suitability score count as "suitable
 * enough" (vegetation's 0.5 score is still a provisional placeholder per
 * suitability_scoring.py's own comment, so it's deliberately excluded from
 * point generation until that scoring is settled). Candidates are then
 * greedily thinned so two adjacent high-scoring cells don't produce two
 * redundant, too-close-together samples, and capped at a maximum count so
 * a large contiguous bare-soil patch can't hand a field technician an
 * unrealistically long list of points to visit.
 */
class SamplingPointGenerator
{
    // Minimum distance between two generated points. 15m (the original
    // default) was closer to research-grade grid-sampling density than
    // real small-site practice -- standard precision-ag grid soil sampling
    // uses 30-61m spacing, though finer spacing is normal for small areas
    // like this system's capped 4ha AOI. 25m is a middle ground: denser
    // than farm-scale norms (justified -- this is site suitability
    // screening on a small plot, not large-field nutrient mapping) without
    // being as tight as the original arbitrary default. Still a fixed
    // constant, not user-configurable yet.
    private const MIN_SPACING_METERS = 25.0;

    // Hard cap on points per run. Spacing alone only controls how close
    // two points can be to each other -- it does nothing to limit how many
    // get generated in total, so a large contiguous bare-soil patch could
    // previously produce dozens+ of points with nothing stopping it. A
    // field technician manually operating a soil probe at each point can't
    // realistically visit an unbounded list in one deployment, so this
    // caps the list at the top-priority candidates instead.
    private const MAX_POINTS = 25;

    /**
     * @return \Illuminate\Support\Collection<int, SamplingPoint>
     */
    public function generate(ClassificationRun $run): \Illuminate\Support\Collection
    {
        // Existing points from a prior run on this same orthomosaic aren't
        // touched here -- what happens to superseded points is left to the
        // follow-up assignment/transmission work, not solved in this pass.
        $candidates = SamplingGrid::where('run_id', $run->id)
            ->where('dominant_cover', 'bare_soil')
            ->where('suitability_score', 1.0)
            ->orderByDesc('suitability_score')
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $latLngPairs = $candidates
            ->map(fn (SamplingGrid $cell) => [$cell->center_latitude, $cell->center_longitude])
            ->all();

        $local = PolygonGeometry::toLocalMeters($latLngPairs);
        $localPoints = $local['points'];

        $accepted = [];
        $acceptedLocalPoints = [];

        foreach ($candidates as $index => $cell) {
            if (count($accepted) >= self::MAX_POINTS) {
                break;
            }

            $point = $localPoints[$index];

            $tooClose = false;
            foreach ($acceptedLocalPoints as $existing) {
                $distance = hypot($point[0] - $existing[0], $point[1] - $existing[1]);
                if ($distance < self::MIN_SPACING_METERS) {
                    $tooClose = true;
                    break;
                }
            }

            if ($tooClose) {
                continue;
            }

            $acceptedLocalPoints[] = $point;
            $accepted[] = $cell;
        }

        $created = collect();

        foreach ($accepted as $rank => $cell) {
            $created->push(SamplingPoint::create([
                'grid_id' => $cell->id,
                'assigned_to' => null,
                'latitude' => $cell->center_latitude,
                'longitude' => $cell->center_longitude,
                'priority_rank' => $rank + 1,
                'status' => 'pending',
            ]));
        }

        return $created;
    }
}
