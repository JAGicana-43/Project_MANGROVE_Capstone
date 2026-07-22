<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Project MANGROVE database...');

        $this->call([
            RoleSeeder::class,  // must run first — users depend on roles
            UserSeeder::class,
            ProjectSeeder::class,
        ]);

        $this->command->info('Database seeding complete.');
    }
}
