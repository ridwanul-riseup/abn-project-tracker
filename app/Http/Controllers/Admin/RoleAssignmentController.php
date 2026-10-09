<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Module;
use App\Models\Product;
use App\Models\Repository;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Rules\NoSecrets;
use App\Support\Access\Capabilities;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gives a user a role on one scope (application-wide, product, application, repository or module).
 * Assignments are never deleted: revoking keeps the row for the history.
 */
class RoleAssignmentController extends Controller
{
    public const SCOPE_MODELS = [
        'product' => Product::class,
        'application' => Application::class,
        'repository' => Repository::class,
        'module' => Module::class,
    ];

    public function store(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('access.manage');

        if ($user->role?->key === Role::SUPER_ADMIN) {
            return back()->with('error', 'Super Admins already have every permission everywhere.');
        }
        if ($user->is($this->user()) && ! $this->access()->isSuperAdmin($this->user())) {
            return back()->with('error', 'You cannot assign roles to yourself.');
        }

        $validated = $request->validate([
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->whereNot('key', Role::SUPER_ADMIN)],
            'assignment_scope_type' => ['required', Rule::in(RoleAssignment::SCOPE_TYPES)],
            'assignment_scope_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:255', new NoSecrets],
        ], [], ['role_id' => 'role', 'assignment_scope_type' => 'scope', 'assignment_scope_id' => 'resource']);

        /** @var Role $role */
        $role = Role::query()->findOrFail($validated['role_id']);
        $scopeType = $validated['assignment_scope_type'];
        $scopeId = null;

        if ($scopeType === 'global') {
            $adminPermissions = array_intersect(Capabilities::GLOBAL_ONLY, $role->permissions()->pluck('key')->all());
            if (array_diff($adminPermissions, ['products.create']) !== [] && ! $this->access()->isSuperAdmin($this->user())) {
                throw ValidationException::withMessages(['role_id' => 'Only a Super Admin can assign a role with administration permissions application-wide.']);
            }
        } else {
            $scopeId = (int) ($validated['assignment_scope_id'] ?? 0);
            if (! self::SCOPE_MODELS[$scopeType]::query()->whereKey($scopeId)->exists()) {
                throw ValidationException::withMessages(['assignment_scope_id' => 'Select the resource this role applies to.']);
            }
        }

        $assignment = DB::transaction(function () use ($user, $role, $scopeType, $scopeId, $validated) {
            $exists = RoleAssignment::query()->active()
                ->where(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => $scopeType])
                ->when($scopeId === null, fn ($q) => $q->whereNull('scope_id'), fn ($q) => $q->where('scope_id', $scopeId))
                ->lockForUpdate()
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages(['role_id' => 'The user already has this role on this scope.']);
            }

            $assignment = RoleAssignment::query()->create([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'reason' => $validated['reason'] ?? null,
                'granted_by' => $this->user()->id,
            ]);
            Audit::record('role.assigned', $assignment, [
                'user' => $user->email,
                'role' => $role->key,
                'scope' => $assignment->scopeLabel(),
                'reason' => $validated['reason'] ?? null,
            ]);

            return $assignment;
        });

        return back()->with('status', $role->name.' role assigned: '.$assignment->scopeLabel().'.');
    }

    public function revoke(RoleAssignment $assignment): RedirectResponse
    {
        Gate::authorize('access.manage');

        if ($assignment->revoked_at !== null) {
            return back()->with('error', 'This role assignment was already revoked.');
        }

        $assignment->forceFill(['revoked_at' => now(), 'revoked_by' => $this->user()->id])->save();
        Audit::record('role.unassigned', $assignment, [
            'user' => $assignment->user->email,
            'role' => $assignment->role->key,
            'scope' => $assignment->scopeLabel(),
        ]);

        return back()->with('status', 'Role assignment revoked.');
    }
}
