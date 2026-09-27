<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ProjectWorkspaceOverviewTest extends TestCase
{
    public function test_overview_view_renders_project_specific_workspace_data(): void
    {
        $user = new User([
            'id' => 'user-researcher',
            'name' => 'Ada Lovelace',
        ]);
        $user->setRelation('role', new Role(['name' => 'researcher']));

        $project = new Project([
            'id' => 'project-workspace',
            'name' => 'Coastal Rehabilitation Trial',
            'description' => 'A mangrove restoration pilot project.',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $html = View::make('projects.overview', [
            'user' => $user,
            'project' => $project,
            'aoiCount' => 2,
            'droneMissionCount' => 1,
            'samplingPointCount' => 4,
            'soilReadingCount' => 2,
        ])->render();

        $this->assertStringContainsString('Coastal Rehabilitation Trial', $html);
        $this->assertStringContainsString('Workflow Progress', $html);
        $this->assertStringContainsString('Project Map', $html);
        $this->assertStringContainsString('2', $html);
    }

    public function test_workspace_section_view_renders_project_specific_section_content(): void
    {
        $project = new Project([
            'id' => 'project-workspace',
            'name' => 'Coastal Rehabilitation Trial',
            'description' => 'A mangrove restoration pilot project.',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $html = View::make('projects.workspace-section', [
            'project' => $project,
            'currentRoute' => 'aoi',
            'title' => 'Area of Interest',
            'subtitle' => 'Map and manage survey boundaries for this workspace.',
            'icon' => 'fa-draw-polygon',
            'emptyMessage' => 'No areas of interest defined yet for this project.',
            'items' => collect(),
        ])->render();

        $this->assertStringContainsString('Area of Interest', $html);
        $this->assertStringContainsString('No areas of interest defined yet for this project.', $html);
    }

    public function test_workspace_layout_renders_role_badge_and_footer(): void
    {
        $user = new User([
            'id' => 'user-researcher',
            'name' => 'Ada Lovelace',
        ]);
        $user->setRelation('role', new Role(['name' => 'researcher']));

        $this->actingAs($user);

        $project = new Project([
            'id' => 'project-workspace',
            'name' => 'Coastal Rehabilitation Trial',
            'description' => 'A mangrove restoration pilot project.',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $html = View::make('projects.overview', [
            'user' => $user,
            'project' => $project,
            'aoiCount' => 2,
            'droneMissionCount' => 1,
            'samplingPointCount' => 4,
            'soilReadingCount' => 2,
        ])->render();

        $this->assertStringContainsString('researcher', $html);
        $this->assertStringContainsString('© 2026 Project M.A.N.G.R.O.V.E', $html);
    }
}
