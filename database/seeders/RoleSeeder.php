<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'permissions' => [
                    'manage_users',
                    'manage_roles',
                    'manage_projects',
                    'manage_aoi',
                    'upload_orthomosaic',
                    'run_classification',
                    'view_classification',
                    'manage_sampling',
                    'view_soil_readings',
                    'generate_reports',
                    'view_reports',
                    'view_audit_logs',
                    'manage_drone_missions',
                    'compare_surveys',
                ],
            ],
            [
                'name' => 'researcher',
                'permissions' => [
                    'manage_projects',
                    'manage_aoi',
                    'upload_orthomosaic',
                    'run_classification',
                    'view_classification',
                    'manage_sampling',
                    'view_soil_readings',
                    'generate_reports',
                    'view_reports',
                    'manage_drone_missions',
                    'compare_surveys',
                ],
            ],
            [
                'name' => 'field_technician',
                'permissions' => [
                    'view_sampling',
                    'collect_soil_readings',
                    'view_reports',
                ],
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                ['permissions' => $role['permissions']]
            );
        }

        $this->command->info('Roles seeded: admin, researcher, field_technician');
    }
}
