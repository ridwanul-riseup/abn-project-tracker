<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Access\AccessService;
use App\Support\Access\Capabilities;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Creates roles and permissions. Names, descriptions and role-permission
 * assignments are only initialised for newly created roles, so admin changes
 * are preserved (Super Admin always receives every permission).
 *
 * Upgrading from the coarse permissions (inventory.write, docs.write, tasks.manage):
 * every role holding one receives the per-module permissions it replaces, every
 * existing role receives the "view" permissions (which were implicit before),
 * and the old permissions are removed.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Capabilities::all() as $key => [$group, $name, $description]) {
            Permission::query()->updateOrCreate(['key' => $key], compact('group', 'name', 'description'));
        }

        DB::transaction(fn () => $this->convertLegacyPermissions());

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

        app(AccessService::class)->flush();
    }

    private function convertLegacyPermissions(): void
    {
        $legacy = Permission::query()->whereIn('key', array_keys(Capabilities::LEGACY))->get();
        if ($legacy->isEmpty()) {
            return;
        }

        $ids = Permission::query()->pluck('id', 'key');
        $views = $ids->only(Capabilities::viewPermissions())->values()->all();

        foreach (Role::query()->with('permissions')->get() as $role) {
            $add = $views;
            foreach ($role->permissions->whereIn('key', array_keys(Capabilities::LEGACY)) as $old) {
                $add = [...$add, ...$ids->only(Capabilities::LEGACY[$old->key])->values()->all()];
            }
            $role->permissions()->syncWithoutDetaching(array_unique($add));
        }

        Permission::query()->whereKey($legacy->modelKeys())->each(function (Permission $permission) {
            $permission->roles()->detach();
            $permission->delete();
        });
    }
}
