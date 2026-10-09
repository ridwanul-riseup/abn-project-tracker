<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the initial Super Admin from HUB_ADMIN_* environment variables.
 * No credentials are stored in source code.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('hub.admin.email');

        if (User::query()->where('email', $email)->exists()) {
            $this->command->info("Super Admin {$email} already exists — skipped.");

            return;
        }

        $password = (string) config('hub.admin.password');
        $generated = $password === '';
        if ($generated) {
            $password = Str::password(20);
        }

        User::query()->create([
            'name' => (string) config('hub.admin.name'),
            'email' => $email,
            'password' => $password,
            'role_id' => Role::query()->where('key', Role::SUPER_ADMIN)->value('id'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        if ($generated) {
            $this->command->warn("Super Admin created: {$email}");
            $this->command->warn("Generated password (shown once, change it after login): {$password}");
        } else {
            $this->command->info("Super Admin created: {$email} (password from HUB_ADMIN_PASSWORD)");
        }
    }
}
