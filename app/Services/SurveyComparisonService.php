<?php

namespace App\Services;

use App\Models\ClassificationRun;
use App\Models\Orthomosaic;
use App\Models\SamplingGrid;
use App\Models\SurveyComparison;
use App\Models\User;
use App\Services\Geo\PolygonGeometry;
use Illuminate\Support\Collection;

/**
 * Matches SamplingGrid cells between two independent ClassificationRuns
 * (different drone flights of the same AreaOfInterest) by real-world
 * nearest-centroid, since grid_x/grid_y indices are only meaningful within
 * a single run's own tiling origin. Pure PHP computation over already-
 * stored Postgres rows -- no Python subprocess involved.
 */
class SurveyComparisonService
{
    private const IMPROVED_TARGET = 'vegetation';
    private const REGRESSED_TARGETS = ['bare_soil', 'open_water'];

    /**
     * Reuses an existing comparison for this exact (aoi, earlier, later)
     * triple instead of recomputing/duplicating it -- the building block
     * the timeline view chains across an AOI's whole survey history, since
     * re-walking the same consecutive pair on every timeline visit would
     * otherwise create a fresh row (and a fresh matching pass) every time.
     */
    public function findOrCompare(Orthomosaic $earlier, Orthomosaic $later, ?User $generatedBy = null): SurveyComparison
    {
        $existing = SurveyComparison::where('aoi_id', $earlier->aoi_id)
            ->where('orthomosaic_a_id', $earlier->id)
            ->where('orthomosaic_b_id', $later->id)
            ->first();

        return $existing ?? $this->compare($earlier, $later, $generatedBy);
    }

    public function compare(Orthomosaic $earlier, Orthomosaic $later, ?User $generatedBy = null): SurveyComparison
    {
        $runA = $this->latestCompletedRun($earlier);
        $runB = $this->latestCompletedRun($later);

        abort_unless($runA && $runB, 422, 'Both orthomosaics must have a completed classification run before they can be compared.');

        $cellsA = $this->scoredCells($runA);
        $cellsB = $this->scoredCells($runB);

        $matches = $this->matchCells($cellsA, $cellsB);

        $changeSummary = $this->summarize($cellsA, $cellsB, $matches);

        return SurveyComparison::create([
            'aoi_id' => $earlier->aoi_id,
            'orthomosaic_a_id' => $earlier->id,
            'orthomosaic_b_id' => $later->id,
            'generated_by' => $generatedBy?->id,
            'change_summary' => $changeSummary,
            'compared_at' => now(),
        ]);
    }

    private function latestCompletedRun(Orthomosaic $orthomosaic): ?ClassificationRun
    {
        return $orthomosaic->classificationRuns()
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first();
    }

    /** @return Collection<int, SamplingGrid> only cells with a usable dominant_cover */
    private function scoredCells(ClassificationRun $run): Collection
    {
        return $run->samplingGrids()
            ->whereNotNull('dominant_cover')
            ->where('dominant_cover', '!=', 'insufficient_data')
            ->get();
    }

    /**
     * Nearest-centroid match in a shared local-meter tangent plane.
     *
     * @return array<int, array{a: SamplingGrid, b: SamplingGrid, distance: float}>
     */
    private function matchCells(Collection $cellsA, Collection $cellsB): array
    {
        if ($cellsA->isEmpty() || $cellsB->isEmpty()) {
            return [];
        }

        $latLngPairs = $cellsA->map(fn (SamplingGrid $cell) => [$cell->center_latitude, $cell->center_longitude])
            ->concat($cellsB->map(fn (SamplingGrid $cell) => [$cell->center_latitude, $cell->center_longitude]))
            ->all();

        $projected = PolygonGeometry::toLocalMeters($latLngPairs);
        $points = $projected['points'];

        $pointsA = array_slice($points, 0, $cellsA->count());
        $pointsB = array_slice($points, $cellsA->count());

        $maxDistance = (float) config('classification.comparison_match_max_distance_meters', 5.0);

        $cellsBValues = $cellsB->values();
        $usedB = [];
        $matches = [];

        foreach ($cellsA->values() as $indexA => $cellA) {
            [$ax, $ay] = $pointsA[$indexA];

            $bestIndex = null;
            $bestDistance = null;

            foreach ($pointsB as $indexB => [$bx, $by]) {
                if (isset($usedB[$indexB])) {
                    continue;
                }

                $distance = sqrt((($ax - $bx) ** 2) + (($ay - $by) ** 2));

                if ($distance <= $maxDistance && ($bestDistance === null || $distance < $bestDistance)) {
                    $bestDistance = $distance;
                    $bestIndex = $indexB;
                }
            }

            if ($bestIndex !== null) {
                $usedB[$bestIndex] = true;
                $matches[] = [
                    'a' => $cellA,
                    'b' => $cellsBValues[$bestIndex],
                    'distance' => $bestDistance,
                ];
            }
        }

        return $matches;
    }

    private function transition(string $coverBefore, string $coverAfter): string
    {
        if ($coverBefore === $coverAfter) {
            return 'unchanged';
        }

        if (in_array($coverBefore, self::REGRESSED_TARGETS, true) && $coverAfter === self::IMPROVED_TARGET) {
            return 'improved';
        }

        if ($coverBefore === self::IMPROVED_TARGET && in_array($coverAfter, self::REGRESSED_TARGETS, true)) {
            return 'declined';
        }

        // Any other real change (e.g. bare_soil <-> open_water) is a land-
        // cover shift but not a mangrove-suitability improvement/decline.
        return 'unchanged';
    }

    private function summarize(Collection $cellsA, Collection $cellsB, array $matches): array
    {
        $transitionCounts = ['improved' => 0, 'declined' => 0, 'unchanged' => 0];
        $coverBreakdown = [];
        $scoreSumBefore = 0.0;
        $scoreSumAfter = 0.0;
        $cellDetails = [];

        foreach ($matches as $match) {
            /** @var SamplingGrid $cellA */
            $cellA = $match['a'];
            /** @var SamplingGrid $cellB */
            $cellB = $match['b'];

            $transition = $this->transition($cellA->dominant_cover, $cellB->dominant_cover);
            $transitionCounts[$transition]++;

            $breakdownKey = $cellA->dominant_cover . '_to_' . $cellB->dominant_cover;
            $coverBreakdown[$breakdownKey] = ($coverBreakdown[$breakdownKey] ?? 0) + 1;

            $scoreSumBefore += (float) $cellA->suitability_score;
            $scoreSumAfter += (float) $cellB->suitability_score;

            // Per-cell detail for the "what changed" map -- cellB's own
            // bounds (the later survey's own tiling), not cellA's, since
            // the map it's drawn on is orthomosaic B's. Discarded by every
            // caller before this until show.blade.php's change map needed
            // it, so it's additive here rather than changing what the
            // aggregate fields above already mean.
            $cellDetails[] = [
                'bounds' => $cellB->cell_bounds,
                'cover_before' => $cellA->dominant_cover,
                'cover_after' => $cellB->dominant_cover,
                'transition' => $transition,
                'score_before' => $cellA->suitability_score,
                'score_after' => $cellB->suitability_score,
            ];
        }

        $matchedCount = count($matches);

        return [
            'matched_cells' => $matchedCount,
            'unmatched_earlier_cells' => $cellsA->count() - $matchedCount,
            'unmatched_later_cells' => $cellsB->count() - $matchedCount,
            'transition_counts' => $transitionCounts,
            'cover_transition_breakdown' => $coverBreakdown,
            'average_suitability_score_before' => $matchedCount > 0 ? round($scoreSumBefore / $matchedCount, 3) : null,
            'average_suitability_score_after' => $matchedCount > 0 ? round($scoreSumAfter / $matchedCount, 3) : null,
            'cells' => $cellDetails,
        ];
    }
}
