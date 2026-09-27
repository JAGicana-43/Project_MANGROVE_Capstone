<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class LobbyPageTest extends TestCase
{
    public function test_lobby_view_renders_projects_from_the_controller_data(): void
    {
        $user = new User([
            'id' => 'user-1',
            'name' => 'Ada Lovelace',
        ]);
        $user->setRelation('role', new Role(['name' => 'researcher']));

        // Shaped exactly like DashboardController::lobby()'s $projectCards
        // entries -- the view no longer builds cards from raw Project
        // models itself, so this is what actually reaches the template.
        $projectCards = collect([
            [
                'id' => 'project-1',
                'name' => 'Coastal Rehabilitation Trial',
                'status' => 'active',
                'created_at' => 'Sep 02, 2026',
                'updated_at' => '1 day ago',
                'updated_at_raw' => now(),
                'aoi_count' => 3,
                'member_count' => 1,
                'members' => collect([
                    ['name' => 'Grace Hopper', 'avatar_url' => null, 'initial' => 'G'],
                ]),
                'is_creator' => true,
                'phase_index' => 1,
                'phase_labels' => ['AOI', 'Mission', 'Orthomosaic', 'Classify', 'Sample'],
                'aoi_shape_points' => null,
                'orthomosaic_count' => 0,
                'point_count' => 0,
                'needs_labeling' => 0,
                'unanalyzed_orthomosaics' => 0,
            ],
        ]);

        $workSummary = [
            'projects' => 1,
            'area_hectares' => 12.5,
            'aois_mapped' => 3,
            'missions_flown' => 1,
            'orthomosaics_uploaded' => 0,
            'classifications_completed' => 0,
            'sampling_points' => 0,
            'soil_readings_collected' => 0,
            'reports_generated' => 0,
            'comparisons_run' => 0,
            'suitability_rate' => null,
        ];

        $html = View::make('dashboard.lobby', [
            'user' => $user,
            'projectCards' => $projectCards,
            'workSummary' => $workSummary,
            'needsAttention' => collect(),
            'aoiMapPoints' => collect(),
            'suitabilityBreakdown' => collect(),
            'suitabilityWaffle' => [],
            'totalCells' => 0,
            'rehabProgress' => ['comparisons_run' => 0, 'cells_improved' => 0, 'cells_declined' => 0, 'cells_unchanged' => 0],
            'recentComparisons' => collect(),
        ])->render();

        $this->assertStringContainsString('Coastal Rehabilitation Trial', $html);
        $this->assertStringContainsString('Ada Lovelace', $html);
        // The Atlas map's own empty state, since aoiMapPoints is empty here.
        $this->assertStringContainsString('No surveyed areas to show', $html);
    }

    public function test_lobby_view_renders_map_summary_and_project_rail(): void
    {
        $user = new User(['id' => 'user-1', 'name' => 'Ada Lovelace']);
        $user->setRelation('role', new Role(['name' => 'researcher']));

        $projectCards = collect([[
            'id' => 'project-1',
            'name' => 'Needs Attention Trial',
            'status' => 'active',
            'created_at' => 'Sep 02, 2026',
            'updated_at' => '1 day ago',
            'updated_at_raw' => now(),
            'aoi_count' => 1,
            'member_count' => 0,
            'members' => collect(),
            'is_creator' => true,
            'phase_index' => 2,
            'phase_labels' => ['AOI', 'Mission', 'Orthomosaic', 'Classify', 'Sample'],
            'aoi_shape_points' => null,
            'orthomosaic_count' => 1,
            'point_count' => 0,
            'needs_labeling' => 1,
            'unanalyzed_orthomosaics' => 1,
        ]]);

        // One AOI with a real boundary, one with only a center point --
        // both branches of the overview map's boundary-vs-marker logic.
        // Shaped exactly like DashboardController::lobby()'s $aoiMapPoints
        // entries (project_id/area_hectares/point_count/suitability_rate/
        // phase_labels feed the Atlas hover card).
        $aoiMapPoints = collect([
            [
                'project_id' => 'project-1',
                'project_name' => 'Needs Attention Trial',
                'aoi_name' => 'Main Site',
                'area_hectares' => 4.2,
                'point_count' => 12,
                'suitability_rate' => 80,
                'boundary' => [[7.0, 125.0], [7.01, 125.0], [7.01, 125.01]],
                'center' => [7.0, 125.0],
                'phase_index' => 2,
                'phase_label' => 'Orthomosaic',
                'phase_labels' => ['AOI', 'Mission', 'Orthomosaic', 'Classify', 'Sample'],
                'cover_breakdown' => ['bare_soil' => 60.0, 'vegetation' => 30.0, 'open_water' => 10.0],
            ],
            [
                'project_id' => 'project-1',
                'project_name' => 'Needs Attention Trial',
                'aoi_name' => 'Backup Site',
                'area_hectares' => 1.1,
                'point_count' => 0,
                'suitability_rate' => null,
                'boundary' => null,
                'center' => [7.05, 125.05],
                'phase_index' => 0,
                'phase_label' => 'AOI',
                'phase_labels' => ['AOI', 'Mission', 'Orthomosaic', 'Classify', 'Sample'],
                'cover_breakdown' => ['bare_soil' => 0, 'vegetation' => 0, 'open_water' => 0],
            ],
        ]);

        $html = View::make('dashboard.lobby', [
            'user' => $user,
            'projectCards' => $projectCards,
            'workSummary' => [
                'projects' => 1, 'area_hectares' => 5.0, 'aois_mapped' => 2, 'missions_flown' => 1,
                'orthomosaics_uploaded' => 1, 'classifications_completed' => 0, 'sampling_points' => 0,
                'soil_readings_collected' => 4, 'reports_generated' => 1, 'comparisons_run' => 1,
                'suitability_rate' => 75,
            ],
            'needsAttention' => $projectCards,
            'aoiMapPoints' => $aoiMapPoints,
            'suitabilityBreakdown' => collect(),
            'suitabilityWaffle' => [],
            'totalCells' => 100,
            'rehabProgress' => ['comparisons_run' => 1, 'cells_improved' => 8, 'cells_declined' => 0, 'cells_unchanged' => 556],
            'recentComparisons' => collect([
                ['id' => 'comparison-1', 'project_id' => 'project-1', 'aoi_name' => 'Main Site', 'compared_at' => '2 days ago', 'improved' => 8, 'declined' => 0],
            ]),
        ])->render();

        $this->assertStringContainsString('Needs Attention Trial', $html);
        $this->assertStringContainsString('overviewMap', $html);
        $this->assertStringContainsString('Main Site', $html);
        // The map's own empty overlay must NOT show when there are real points.
        $this->assertStringNotContainsString('No surveyed areas to show', $html);
        // The aggregate attention alert (folded into .atlas-summary now,
        // not a separate itemized panel).
        $this->assertStringContainsString('uploaded but never classified', $html);
        $this->assertStringContainsString('awaiting cluster labels', $html);
    }
}
