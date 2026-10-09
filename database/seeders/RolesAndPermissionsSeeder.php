<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Access\Capabilities;
use Illuminate\Database\Seeder;

/**
 * Creates roles and capabilities. Names, descriptions and role-permission
 * assignments are only initialised for newly created roles, so admin changes
 * are preserved (Super Admin always receives every capability).
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
            if ($isNew) {
                $role->fill(['name' => $name, 'description' => $description, 'sort_order' => $sort]);
            }
            $sort++;
            $role->is_system = true;
            $role->save();

            if ($isNew || $key === Role::SUPER_ADMIN) {
                $role->permissions()->sync(
                    Permission::query()->whereIn('key', Capabilities::defaultsByRole()[$key])->pluck('id')
                );
            }
        }
    }
}
