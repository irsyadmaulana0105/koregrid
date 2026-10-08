<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Wajib di semua environment.
        $this->call(RolesAndPermissionsSeeder::class);

        // Melewati dirinya sendiri di luar local/testing.
        $this->call(DemoDataSeeder::class);
    }
}
