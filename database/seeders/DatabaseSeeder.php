<?php

namespace Database\Seeders;

use App\Support\Audit;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Safe initial data: roles, options, the initial Super Admin (from env),
     * the workbook inventory and the shipped Markdown documents.
     */
    public function run(): void
    {
        Audit::disable();

        try {
            $this->call([
                RolesAndPermissionsSeeder::class,
                OptionSeeder::class,
                AdminUserSeeder::class,
                WorkbookSeeder::class,
                DocumentSeeder::class,
                DemoUserSeeder::class,
            ]);
        } finally {
            Audit::enable();
        }
    }
}
