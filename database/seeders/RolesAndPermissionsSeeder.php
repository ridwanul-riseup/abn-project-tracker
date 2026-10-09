<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Access\Capabilities;
use Illuminate\Database\Seeder;

/**
 * Creates roles and capabilities. Existing role-permission assignments are
 * only initialised for newly created roles, so admin changes are preserved.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Capabilities::all() as $key => [$group, $name, $description]) {
            Permission::query()->updateOrCreate(['key' => $key], compact('group', 'name', 'description'));
        }

        $sort = 0;
        foreach (Capabilities::roles() as $key => [$name, $description]) {
            $role = Role::query()->firstOrNew(['key' => $key]);
            $isNew = ! $role->exists;
            $role->fill(['name' => $name, 'description' => $description, 'is_system' => true, 'sort_order' => $sort++])->save();

            if ($isNew || $key === Role::SUPER_ADMIN) {
                $role->permissions()->sync(
                    Permission::query()->whereIn('key', Capabilities::defaultsByRole()[$key])->pluck('id')
                );
            }
        }
    }
}
