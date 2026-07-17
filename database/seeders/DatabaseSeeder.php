<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $seeders = [
            CatalogSeeder::class,
            AchievementSeeder::class,
            AdminUserSeeder::class,
        ];

        if (config('palomnik.demo.enabled')) {
            $seeders[] = DemoSeeder::class;
        }

        $this->call($seeders);
    }
}
