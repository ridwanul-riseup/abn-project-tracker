<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Capability matrix. The Super Admin role is fixed (always all capabilities),
 * and only Super Admins may change administration capabilities.
 */
class RoleController extends Controller
{
    private const ADMIN_GROUP = 'Administration';

    public function index(): View
    {
        Gate::authorize('roles.manage');

        return view('admin.roles.index', [
            'roles' => Role::query()->with('permissions')->withCount('users')->orderBy('sort_order')->get(),
            'permissions' => Permission::query()->orderBy('id')->get()->groupBy('group'),
            'canEditAdmin' => $this->access()->isSuperAdmin($this->user()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $request->validate([
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['array'],
            'capabilities.*.*' => ['string'],
        ]);

        $permissions = Permission::query()->get()->keyBy('key');
        $canEditAdmin = $this->access()->isSuperAdmin($this->user());
        $submitted = (array) $request->input('capabilities', []);
        $changed = 0;

        DB::transaction(function () use ($permissions, $canEditAdmin, $submitted, &$changed) {
            foreach (Role::query()->with('permissions')->get() as $role) {
                if ($role->key === Role::SUPER_ADMIN) {
                    continue;
                }

                $before = $role->permissions->pluck('key')->sort()->values()->all();
                $requested = array_filter((array) ($submitted[$role->id] ?? []), 'is_string');

                // Keep administration capabilities unchanged unless a Super Admin is editing.
                $after = $permissions->filter(function (Permission $p) use ($requested, $before, $canEditAdmin) {
                    if ($p->group === self::ADMIN_GROUP && ! $canEditAdmin) {
                        return in_array($p->key, $before, true);
                    }

                    return in_array($p->key, $requested, true);
                })->keys()->sort()->values()->all();

                if ($before !== $after) {
                    $role->permissions()->sync($permissions->whereIn('key', $after)->pluck('id'));
                    Audit::record('role.capabilities_changed', $role, [
                        'added' => array_values(array_diff($after, $before)),
                        'removed' => array_values(array_diff($before, $after)),
                    ]);
                    $changed++;
                }
            }
        });
        $this->access()->flush();

        return back()->with('status', $changed ? "Updated {$changed} role(s)." : 'No changes.');
    }
}
