<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LocationSeeder::class,
            CategorySeeder::class,
            UserSeeder::class,
        ]);

        if (! app()->isProduction() || (bool) env('SEED_DEMO', false)) {
            $this->call(DemoContentSeeder::class);
        }
    }
}
