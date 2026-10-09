<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessRule;
use App\Models\Application;
use App\Models\Document;
use App\Models\Module;
use App\Models\Product;
use App\Models\Repository;
use App\Models\Role;
use App\Models\User;
use App\Rules\NoSecrets;
use App\Support\Access\AccessService;
use App\Support\Access\Capabilities;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Grants, denials and revocations of scoped access for a user.
 * Rules are never deleted: revoking keeps the row for the grant history.
 */
class AccessRuleController extends Controller
{
    private const SCOPE_MODELS = [
        'product' => Product::class,
        'application' => Application::class,
        'repository' => Repository::class,
        'module' => Module::class,
        'document' => Document::class,
    ];

    public function index(User $user): View
    {
        Gate::authorize('access.manage');
        $user->load('role');
        $access = $this->access();

        $effective = [];
        foreach (['product', 'application', 'repository', 'module'] as $type) {
            $class = self::SCOPE_MODELS[$type];
            $effective[$type] = $this->paginateList($class::query()->orderBy('code'), "effective_{$type}")
                ->through(fn (Model $m) => ['model' => $m, 'level' => $access->level($user, $m)]);
        }
        $documents = $this->paginateList(Document::query()->orderBy('path'), 'effective_document')
            ->through(fn (Model $d) => ['model' => $d, 'level' => $access->level($user, $d)]);

        $roleCapabilities = $access->isSuperAdmin($user)
            ? array_keys(Capabilities::all())
            : ($user->role?->permissions()->pluck('key')->all() ?? []);

        return view('admin.users.access', [
            'target' => $user,
            'activeRules' => $this->paginateList($user->accessRules()->active()->with('granter')->latest('id'), 'active_rules'),
            'revokedRules' => $this->paginateList($user->accessRules()->whereNotNull('revoked_at')->with(['granter', 'revoker'])->latest('revoked_at'), 'revoked_rules'),
            'effective' => $effective,
            'documents' => $documents,
            'scopeChoices' => $this->scopeChoices(),
            'roleAssignments' => $this->paginateList($user->roleAssignments()->active()->with(['role', 'granter'])->latest('id'), 'role_assignments'),
            'revokedAssignments' => $this->paginateList($user->roleAssignments()->whereNotNull('revoked_at')->with(['role', 'granter', 'revoker'])->latest('revoked_at'), 'revoked_assignments'),
            'assignableRoles' => Role::query()->where('key', '!=', Role::SUPER_ADMIN)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id'),
            'capabilities' => Capabilities::all(),
            'roleCapabilities' => $roleCapabilities,
            'targetIsSuperAdmin' => $user->role?->key === Role::SUPER_ADMIN,
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('access.manage');

        if ($user->role?->key === Role::SUPER_ADMIN) {
            return back()->with('error', 'Super Admins always have full access; scoped rules do not apply to them.');
        }

        $validated = $request->validate([
            'scope_type' => ['required', Rule::in(AccessService::SCOPE_TYPES)],
            'scope_id' => ['nullable', 'integer'],
            'permission' => ['required', Rule::in([AccessService::READ, AccessService::WRITE, AccessService::DENY])],
            'reason' => ['nullable', 'string', 'max:255', new NoSecrets],
        ]);

        $scopeType = $validated['scope_type'];
        $scopeId = null;
        if ($scopeType === 'global') {
            if ($validated['permission'] === AccessService::DENY) {
                throw ValidationException::withMessages(['permission' => 'An application-wide deny is not allowed. Deactivate the user instead.']);
            }
        } else {
            $scopeId = (int) ($validated['scope_id'] ?? 0);
            if (! self::SCOPE_MODELS[$scopeType]::query()->whereKey($scopeId)->exists()) {
                throw ValidationException::withMessages(['scope_id' => 'Select the resource this rule applies to.']);
            }
        }

        $rule = DB::transaction(function () use ($user, $scopeType, $scopeId, $validated) {
            $existing = AccessRule::query()->active()
                ->where('user_id', $user->id)
                ->where('scope_type', $scopeType)
                ->when($scopeId === null, fn ($q) => $q->whereNull('scope_id'), fn ($q) => $q->where('scope_id', $scopeId))
                ->lockForUpdate()
                ->get();

            foreach ($existing as $old) {
                $old->forceFill(['revoked_at' => now(), 'revoked_by' => $this->user()->id])->save();
                Audit::record('access.revoked', $old, ['user' => $user->email, 'reason' => 'Replaced by a new rule']);
            }

            $rule = AccessRule::query()->create([
                'user_id' => $user->id,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'permission' => $validated['permission'],
                'reason' => $validated['reason'] ?? null,
                'granted_by' => $this->user()->id,
            ]);
            Audit::record($validated['permission'] === AccessService::DENY ? 'access.denied' : 'access.granted', $rule, [
                'user' => $user->email,
                'permission' => $validated['permission'],
                'scope' => $rule->scopeLabel(),
                'reason' => $validated['reason'] ?? null,
            ]);

            return $rule;
        });

        return back()->with('status', ucfirst($rule->permission).' rule added: '.$rule->scopeLabel().'.');
    }

    public function revoke(AccessRule $rule): RedirectResponse
    {
        Gate::authorize('access.manage');

        if ($rule->revoked_at !== null) {
            return back()->with('error', 'This rule was already revoked.');
        }

        $rule->forceFill(['revoked_at' => now(), 'revoked_by' => $this->user()->id])->save();
        Audit::record('access.revoked', $rule, ['user' => $rule->user->email, 'scope' => $rule->scopeLabel(), 'permission' => $rule->permission]);

        return back()->with('status', 'Rule revoked.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function scopeChoices(): array
    {
        $choices = [];
        foreach (self::SCOPE_MODELS as $type => $class) {
            $choices[$type] = $class::query()->orderBy($type === 'document' ? 'path' : 'code')->get()
                ->mapWithKeys(fn (Model $m) => [$m->getKey() => $type === 'document'
                    ? $m->getAttribute('title').' ('.$m->getAttribute('path').')'
                    : $m->getAttribute('code').' — '.$m->getAttribute('name')])
                ->all();
        }

        return $choices;
    }
}
