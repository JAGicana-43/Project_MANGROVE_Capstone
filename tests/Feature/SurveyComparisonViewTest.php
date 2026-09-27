<?php

namespace Tests\Feature;

use App\Models\AreaOfInterest;
use App\Models\Orthomosaic;
use App\Models\Project;
use App\Models\SurveyComparison;
use App\Models\User;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class SurveyComparisonViewTest extends TestCase
{
    public function test_index_view_renders_empty_state_with_no_comparable_orthomosaics(): void
    {
        $project = new Project([
            'id' => 'project-comparisons',
            'name' => 'Coastal Rehabilitation Trial',
            'status' => 'active',
        ]);

        $html = View::make('projects.comparisons.index', [
            'user' => new User(['id' => 'user-researcher', 'name' => 'Ada Lovelace']),
            'project' => $project,
            'currentRoute' => 'comparisons',
            'aois' => collect(),
            'comparableOrthomosaicsByAoi' => collect(),
            'comparisons' => collect(),
        ])->render();

        $this->assertStringContainsString('Compare Surveys', $html);
        $this->assertStringContainsString('at least two classified orthomosaics', $html);
    }

    public function test_show_view_renders_change_summary(): void
    {
        $project = new Project(['id' => 'project-comparisons', 'name' => 'Coastal Rehabilitation Trial', 'status' => 'active']);

        $aoi = new AreaOfInterest(['id' => 'aoi-1', 'name' => 'North Cove Plot']);

        $orthomosaicA = new Orthomosaic(['id' => 'ortho-a', 'uploaded_at' => now()->subMonths(3)]);
        $orthomosaicB = new Orthomosaic(['id' => 'ortho-b', 'uploaded_at' => now()]);

        $comparison = new SurveyComparison([
            'id' => 'comparison-1',
            'change_summary' => [
                'matched_cells' => 564,
                'unmatched_earlier_cells' => 0,
                'unmatched_later_cells' => 0,
                'transition_counts' => ['improved' => 8, 'declined' => 0, 'unchanged' => 556],
                'cover_transition_breakdown' => [
                    'bare_soil_to_vegetation' => 8,
                    'bare_soil_to_bare_soil' => 156,
                ],
                'average_suitability_score_before' => 0.607,
                'average_suitability_score_after' => 0.6,
            ],
            'compared_at' => now(),
        ]);
        $comparison->setRelation('areaOfInterest', $aoi);
        $comparison->setRelation('orthomosaicA', $orthomosaicA);
        $comparison->setRelation('orthomosaicB', $orthomosaicB);

        $html = View::make('projects.comparisons.show', [
            'user' => new User(['id' => 'user-researcher', 'name' => 'Ada Lovelace']),
            'project' => $project,
            'currentRoute' => 'comparisons',
            'comparison' => $comparison,
            'runA' => null,
            'runB' => null,
        ])->render();

        $this->assertStringContainsString('North Cove Plot', $html);
        $this->assertStringContainsString('564', $html);
        $this->assertStringContainsString('Bare Soil', $html);
        $this->assertStringContainsString('Vegetation', $html);
    }
}
