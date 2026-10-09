<?php

namespace Database\Seeders;

use App\Models\AccessRule;
use App\Models\Module;
use App\Models\Product;
use App\Models\Repository;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * LOCAL DEVELOPMENT ONLY — creates one user per role with sample access so the
 * permission model can be explored manually. Never run in production.
 *
 *   php artisan db:seed --class=DemoUserSeeder
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('DemoUserSeeder refuses to run in production.');

            return;
        }

        $password = 'password'; // Str::password(16, symbols: false);
        $ott = Product::query()->where('code', 'OTT')->first();
        $shr = Product::query()->where('code', 'SHR')->first();

        $users = [
            ['Project Admin (demo)', 'project.admin@abhub.local', Role::PROJECT_ADMIN, [['global', null, 'write']]],
            ['Project Manager (demo)', 'pm@abhub.local', Role::PROJECT_MANAGER, [['product', $ott?->id, 'write']]],
            ['Developer (demo)', 'developer@abhub.local', Role::DEVELOPER, [['product', $ott?->id, 'read'], ['product', $shr?->id, 'read']]],
            ['QA Engineer (demo)', 'qa@abhub.local', Role::QA_ENGINEER, [['product', $ott?->id, 'read']]],
            ['Viewer (demo)', 'viewer@abhub.local', Role::VIEWER, [['product', $ott?->id, 'read']]],
        ];

        foreach ($users as [$name, $email, $roleKey, $rules]) {
            $user = User::query()->updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => $password,
                'role_id' => Role::query()->where('key', $roleKey)->value('id'),
                'is_active' => true,
            ]);

            foreach ($rules as [$type, $id, $permission]) {
                if ($type !== 'global' && $id === null) {
                    continue;
                }
                AccessRule::query()->firstOrCreate(
                    ['user_id' => $user->id, 'scope_type' => $type, 'scope_id' => $id, 'revoked_at' => null],
                    ['permission' => $permission, 'reason' => 'Demo seed'],
                );
            }
        }

        // Scoped roles: the developer leads one OTT repository as Project Manager, and a
        // contractor with only the Viewer role works as Developer on a single module.
        $repository = $ott ? Repository::query()->where('product_id', $ott->id)->orderBy('code')->first() : null;
        $module = $ott ? Module::query()->where('product_id', $ott->id)->orderBy('code')->first() : null;
        $contractor = User::query()->updateOrCreate(['email' => 'module.dev@abhub.local'], [
            'name' => 'Module Developer (demo)',
            'password' => $password,
            'role_id' => Role::query()->where('key', Role::VIEWER)->value('id'),
            'is_active' => true,
        ]);
        foreach ([
            ['developer@abhub.local', Role::PROJECT_MANAGER, $repository],
            [$contractor->email, Role::DEVELOPER, $module],
        ] as [$email, $roleKey, $scope]) {
            if ($scope === null) {
                continue;
            }
            RoleAssignment::query()->firstOrCreate([
                'user_id' => User::query()->where('email', $email)->value('id'),
                'role_id' => Role::query()->where('key', $roleKey)->value('id'),
                'scope_type' => $scope->getMorphClass(),
                'scope_id' => $scope->getKey(),
                'revoked_at' => null,
            ], ['reason' => 'Demo seed']);
        }

        $this->command->warn('Demo users created (password for all: '.$password.')');
    }
}
