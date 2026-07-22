<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectMember;
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

        $project = new Project([
            'id' => 'project-1',
            'name' => 'Coastal Rehabilitation Trial',
            'status' => 'active',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDay(),
        ]);
        $project->setAttribute('aoi_count', 3);
        $project->setAttribute('member_count', 4);

        // A real project member (distinct from the logged-in $user) — proves
        // the avatar stack renders actual members, not a stand-in derived
        // from the creator's name.
        $member = new User(['id' => 'user-2', 'name' => 'Grace Hopper']);
        $projectMember = new ProjectMember(['id' => 'member-1', 'user_id' => 'user-2']);
        $projectMember->setRelation('user', $member);
        $project->setRelation('members', collect([$projectMember]));

        $html = View::make('dashboard.lobby', [
            'user' => $user,
            'projects' => collect([$project]),
        ])->render();

        $this->assertStringContainsString('Coastal Rehabilitation Trial', $html);
        $this->assertStringContainsString('Ada Lovelace', $html);
        $this->assertStringContainsString('Grace Hopper', $html);
    }
}
