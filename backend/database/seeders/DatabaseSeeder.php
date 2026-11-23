<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Model events stay enabled: rooms generate their QR token on "creating".
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AssetTypeSeeder::class,
            CategorySeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoSeeder::class);
        }
    }
}
