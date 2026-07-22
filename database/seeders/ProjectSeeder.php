<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $researcher = User::where('email', 'researcher@mangrove.ph')->first();

        if (!$researcher) {
            $this->command->warn('Researcher demo user not found; skipping project seed.');
            return;
        }

        $project = Project::firstOrCreate(
            ['name' => 'Demo Mangrove Rehabilitation Project'],
            [
                'created_by' => $researcher->id,
                'description' => 'Sample project seeded for the researcher lobby.',
                'status' => 'active',
                'started_at' => now(),
            ]
        );

        ProjectMember::firstOrCreate(
            [
                'project_id' => $project->id,
                'user_id' => $researcher->id,
            ],
            [
                'role' => 'lead',
                'assigned_at' => now(),
            ]
        );

        $this->command->info('Seeded demo project for the researcher lobby.');
    }
}
