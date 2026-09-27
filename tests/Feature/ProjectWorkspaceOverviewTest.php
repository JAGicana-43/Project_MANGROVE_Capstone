<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ProjectWorkspaceOverviewTest extends TestCase
{
    // Only the "role badge and footer" test below calls actingAs(), which
    // makes the shared notification-bell view composer (AppServiceProvider)
    // query the notifications table -- needs a real migrated schema, unlike
    // the other tests in this file that render via View::make() with no
    // auth and never touch the database.
    use RefreshDatabase;

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
