<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('No default accounts created. Use clinic:admin to create your administrator, or --class=DemoSeeder locally.');
    }
}
