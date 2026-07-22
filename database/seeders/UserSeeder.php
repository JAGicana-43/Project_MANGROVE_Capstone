<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin')->first();

        if (!$adminRole) {
            $this->command->error('Admin role not found. Run RoleSeeder first.');
            return;
        }

        // Read from .env (SEED_DEFAULT_PASSWORD) rather than hardcoding a
        // real credential here -- this file is committed to git.
        $defaultPassword = env('SEED_DEFAULT_PASSWORD', 'ChangeMe123!');

        // Default admin account
        User::firstOrCreate(
            ['email' => 'admin@mangrove.ph'],
            [
                'role_id'  => $adminRole->id,
                'name'     => 'System Administrator',
                'password' => Hash::make($defaultPassword),
                'is_active' => true,
            ]
        );

        $researcherRole = Role::where('name', 'researcher')->first();
        if ($researcherRole) {
            User::firstOrCreate(
                ['email' => 'researcher@mangrove.ph'],
                [
                    'role_id'  => $researcherRole->id,
                    'name'     => 'Researcher Demo',
                    'password' => Hash::make($defaultPassword),
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Default admin user created.');
        $this->command->info('Researcher demo user available: researcher@mangrove.ph');
        $this->command->info('Email:    admin@mangrove.ph');
        $this->command->info('Password: set via SEED_DEFAULT_PASSWORD in .env');
        $this->command->warn('IMPORTANT: Change this password after first login!');
    }
}
