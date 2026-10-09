<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Access\Capabilities;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Capability matrix. The Super Admin role is fixed (always all capabilities),
 * and only Super Admins may change administration capabilities.
 */
class RoleController extends Controller
{
    private const ADMIN_GROUP = Capabilities::ADMIN_GROUP;

    public function index(): View
    {
        Gate::authorize('roles.manage');

        $order = array_flip(array_keys(Capabilities::all()));

        return view('admin.roles.index', [
            'roles' => Role::query()->with('permissions')
                ->withCount(['users', 'assignments' => fn ($q) => $q->whereNull('revoked_at')])
                ->orderBy('sort_order')->get(),
            'permissions' => Permission::query()->orderBy('id')->get()
                ->sortBy(fn (Permission $p) => $order[$p->key] ?? PHP_INT_MAX)->groupBy('group'),
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

    /**
     * Create a custom role. It starts without capabilities.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $request->merge(['key' => Str::of($request->input('key') ?: $request->input('name', ''))->lower()->slug('_')->toString()]);
        $validated = $request->validate($this->detailRules(null) + [
            'key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9][a-z0-9_]*$/', Rule::unique('roles', 'key')],
        ]);

        $role = Role::query()->create([
            'key' => $validated['key'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
            'sort_order' => (int) Role::query()->max('sort_order') + 1,
        ]);
        Audit::record('role.created', $role, ['after' => $role->only(['key', 'name', 'description'])]);

        return back()->with('status', "Role “{$role->name}” created. Tick its capabilities below and save.");
    }

    /**
     * Rename a role or change its description. The key and the Super Admin role are fixed.
     */
    public function updateDetails(Request $request, Role $role): RedirectResponse
    {
        Gate::authorize('roles.manage');
        abort_if($role->isSuperAdmin(), 403, 'The Super Admin role cannot be changed.');

        $validated = $request->validate($this->detailRules($role));
        $before = $role->only(['name', 'description']);
        $role->update(['name' => $validated['name'], 'description' => $validated['description'] ?? null]);

        if ($role->wasChanged()) {
            Audit::record('role.updated', $role, ['before' => $before, 'after' => $role->only(['name', 'description'])]);
        }

        return back()->with('status', "Role “{$role->name}” saved.");
    }

    /**
     * Delete a custom role that no user has. System roles cannot be deleted.
     */
    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('roles.manage');

        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }
        $users = $role->users()->count();
        if ($users > 0) {
            return back()->with('error', "“{$role->name}” is assigned to {$users} user(s). Reassign them first.");
        }
        if ($role->assignments()->exists()) {
            return back()->with('error', "“{$role->name}” has scoped role assignments (active or in the history) and cannot be deleted.");
        }

        DB::transaction(function () use ($role) {
            Audit::record('role.deleted', $role, ['before' => $role->only(['key', 'name', 'description'])]);
            $role->permissions()->detach();
            $role->delete();
        });
        $this->access()->flush();

        return back()->with('status', "Role “{$role->name}” deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function detailRules(?Role $role): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
