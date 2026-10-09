<?php

namespace App\Support\Access;

use App\Models\AccessRule;
use App\Models\Application;
use App\Models\ComponentDependency;
use App\Models\Module;
use App\Models\Repository;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for authorization decisions.
 *
 * Precedence (see docs/ACCESS_CONTROL.md):
 *  1. Inactive users have no access.
 *  2. Super Admins have full access (cannot be denied, prevents lock-out).
 *  3. An explicit DENY on the resource or any ancestor scope removes access.
 *  4. Otherwise the most specific explicit grant wins
 *     (document > module/repository > application > product > global).
 *  5. Records open to all users (e.g. general documents) default to READ.
 *  6. Otherwise no access.
 *
 * Scoped role assignments count as grants on their scope (WRITE when the role
 * has a content-changing permission, otherwise READ) and are merged with the
 * explicit rules of the same scope; a deny still always wins.
 *
 * Permissions limit which actions a user may take inside the scopes they can
 * reach. They come from the user's global role, from application-wide role
 * assignments, and (for that subtree only) from scoped role assignments.
 */
class AccessService
{
    public const READ = 'read';

    public const WRITE = 'write';

    public const DENY = 'deny';

    /** Scope types an access rule can target. */
    public const SCOPE_TYPES = ['global', 'product', 'application', 'repository', 'module', 'document'];

    /** Lower rank = more specific. */
    private const RANK = [
        'document' => 0,
        'module' => 1,
        'repository' => 1,
        'application' => 2,
        'product' => 3,
        'global' => 4,
    ];

    /** @var array<int, array<string, string>> */
    private array $rules = [];

    /** @var array<int, list<string>> */
    private array $capabilities = [];

    /** @var array{repository: array<int, array{0: ?int, 1: int}>, module: array<int, array{0: ?int, 1: int}>, application: array<int, int>}|null */
    private ?array $graph = null;

    /** @var array<string, list<int>> */
    private array $visible = [];

    /** @var array<int, array<string, list<int>>> user id => scope key => role ids */
    private array $assignments = [];

    /** @var array<int, list<string>>|null role id => permission keys */
    private ?array $rolePermissions = null;

    public function flush(): void
    {
        $this->rules = [];
        $this->capabilities = [];
        $this->graph = null;
        $this->visible = [];
        $this->assignments = [];
        $this->rolePermissions = null;
    }

    public function isSuperAdmin(User $user): bool
    {
        return $user->is_active && $user->role?->key === Role::SUPER_ADMIN;
    }

    /**
     * Application-wide permission: from the global role or an application-wide role assignment.
     */
    public function hasCapability(User $user, string $permission): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($this->isSuperAdmin($user) || in_array($permission, $this->capabilitiesFor($user), true)) {
            return true;
        }

        return $this->assignedRolesHave($this->assignmentsFor($user)['global'] ?? [], $permission);
    }

    /**
     * Permission for an action on (or inside) a resource: application-wide, or from a
     * role assigned on the resource or one of its ancestors. Access level is checked separately.
     */
    public function can(User $user, string $permission, ?Model $resource = null): bool
    {
        if ($this->hasCapability($user, $permission)) {
            return true;
        }

        if ($resource === null || ! $user->is_active || Capabilities::isGlobalOnly($permission)) {
            return false;
        }

        $assignments = $this->assignmentsFor($user);
        foreach (array_keys($this->nodesFor($resource)) as $key) {
            if ($key !== 'global' && $this->assignedRolesHave($assignments[$key] ?? [], $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the user holds a permission application-wide or on at least one scope
     * (used for menus and "new" buttons before a scope is chosen).
     */
    public function hasCapabilityAnywhere(User $user, string $permission): bool
    {
        if ($this->hasCapability($user, $permission)) {
            return true;
        }

        if (! $user->is_active || Capabilities::isGlobalOnly($permission)) {
            return false;
        }

        foreach ($this->assignmentsFor($user) as $roleIds) {
            if ($this->assignedRolesHave($roleIds, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Effective access level: 'write', 'read' or null (no access).
     */
    public function level(User $user, Model $resource): ?string
    {
        if (! $user->is_active) {
            return null;
        }

        if ($this->isSuperAdmin($user)) {
            return self::WRITE;
        }

        if ($resource instanceof ComponentDependency) {
            return $this->dependencyLevel($user, $resource);
        }

        $rules = $this->rulesFor($user);
        $best = null;
        $bestRank = PHP_INT_MAX;

        foreach ($this->nodesFor($resource) as $key => $type) {
            $permission = $rules[$key] ?? null;
            if ($permission === null) {
                continue;
            }
            if ($permission === self::DENY) {
                return null;
            }
            $rank = self::RANK[$type];
            if ($rank < $bestRank) {
                $best = $permission;
                $bestRank = $rank;
            } elseif ($rank === $bestRank && $permission === self::WRITE) {
                $best = self::WRITE;
            }
        }

        if ($best === null && $resource instanceof HasAccessScope && $resource->openToAllUsers()) {
            return self::READ;
        }

        return $best;
    }

    /**
     * Read access to the scope plus the "view" permission of the record's feature module.
     */
    public function canRead(User $user, ?Model $resource): bool
    {
        return $resource !== null && $this->level($user, $resource) !== null && $this->mayView($user, $resource);
    }

    public function canWrite(User $user, ?Model $resource): bool
    {
        return $resource !== null && $this->level($user, $resource) === self::WRITE && $this->mayView($user, $resource);
    }

    private function mayView(User $user, Model $resource): bool
    {
        $permission = Capabilities::for($resource, 'view');

        return $permission === null || $this->can($user, $permission, $resource);
    }

    /**
     * Write access at application-wide level (used for records without a parent scope).
     */
    public function hasGlobalWrite(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $this->isSuperAdmin($user) || ($this->rulesFor($user)['global'] ?? null) === self::WRITE;
    }

    /**
     * IDs of records of the given class the user may read; null means "all".
     *
     * @param  class-string<Model&HasAccessScope>  $class
     * @return list<int>|null
     */
    public function visibleIds(User $user, string $class): ?array
    {
        if ($this->isSuperAdmin($user)) {
            return null;
        }

        $cacheKey = $user->id.'|'.$class;

        if (! isset($this->visible[$cacheKey])) {
            $query = $class::query();
            if ($class === ComponentDependency::class) {
                $query->with(['source', 'target']);
            }

            $this->visible[$cacheKey] = array_values($query->get($class::accessColumns())
                ->filter(fn (Model $model) => $this->canRead($user, $model))
                ->map(fn (Model $model) => (int) $model->getKey())
                ->all());
        }

        return $this->visible[$cacheKey];
    }

    /**
     * Restrict a query (on an access-scoped model) to records the user may read.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function restrict(Builder $query, User $user): Builder
    {
        $model = $query->getModel();
        if (! $model instanceof HasAccessScope) {
            throw new \LogicException(get_class($model).' is not access-scoped.');
        }

        $ids = $this->visibleIds($user, get_class($model));
        if ($ids !== null) {
            $query->whereIn($model->getTable().'.id', $ids);
        }

        return $query;
    }

    /**
     * Scope keys ("type:id" => type) relevant to a resource, including ancestors.
     *
     * @return array<string, string>
     */
    public function nodesFor(Model $resource): array
    {
        $nodes = ['global' => 'global'];
        $selfType = $resource->getMorphClass();

        if (isset(self::RANK[$selfType]) && $resource->getKey() !== null) {
            $this->addNode($nodes, $selfType, (int) $resource->getKey());
        }

        if ($resource instanceof HasAccessScope) {
            foreach ($resource->accessParents() as [$type, $id]) {
                $this->addNode($nodes, $type, $id);
            }
        }

        return $nodes;
    }

    /**
     * @param  array<string, string>  $nodes
     */
    private function addNode(array &$nodes, string $type, int $id): void
    {
        $nodes[$type.':'.$id] = $type;
        $graph = $this->graph();

        if ($type === 'repository' || $type === 'module') {
            [$applicationId, $productId] = $graph[$type][$id] ?? [null, null];
            if ($applicationId !== null) {
                $this->addNode($nodes, 'application', $applicationId);
            }
            if ($productId !== null) {
                $nodes['product:'.$productId] = 'product';
            }
        } elseif ($type === 'application' && isset($graph['application'][$id])) {
            $nodes['product:'.$graph['application'][$id]] = 'product';
        }
    }

    private function dependencyLevel(User $user, ComponentDependency $dependency): ?string
    {
        $source = $dependency->source;
        $target = $dependency->target;

        if ($source === null || $target === null || ! $this->canRead($user, $target)) {
            return null;
        }

        return $this->level($user, $source);
    }

    /**
     * @return array<string, string>
     */
    private function rulesFor(User $user): array
    {
        if (! isset($this->rules[$user->id])) {
            $strength = [self::READ => 1, self::WRITE => 2, self::DENY => 3];
            $map = [];
            $merge = function (string $key, string $permission) use (&$map, $strength) {
                $current = $map[$key] ?? null;
                if ($current === null || $strength[$permission] > $strength[$current]) {
                    $map[$key] = $permission;
                }
            };

            AccessRule::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->get(['scope_type', 'scope_id', 'permission'])
                ->each(fn (AccessRule $rule) => $merge(self::scopeKey($rule->scope_type, $rule->scope_id), $rule->permission));

            foreach ($this->assignmentsFor($user) as $key => $roleIds) {
                $writes = array_filter($roleIds, fn (int $id) => Capabilities::grantsWrite($this->rolePermissions()[$id] ?? []));
                $merge($key, $writes === [] ? self::READ : self::WRITE);
            }

            $this->rules[$user->id] = $map;
        }

        return $this->rules[$user->id];
    }

    public static function scopeKey(string $type, ?int $id): string
    {
        return $type === 'global' ? 'global' : $type.':'.$id;
    }

    /**
     * Active role assignments of a user, grouped by scope key. Super Admin cannot be assigned per scope.
     *
     * @return array<string, list<int>>
     */
    private function assignmentsFor(User $user): array
    {
        if (! isset($this->assignments[$user->id])) {
            $map = [];
            RoleAssignment::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->whereHas('role', fn (Builder $q) => $q->where('key', '!=', Role::SUPER_ADMIN))
                ->get(['scope_type', 'scope_id', 'role_id'])
                ->each(function (RoleAssignment $assignment) use (&$map) {
                    $map[self::scopeKey($assignment->scope_type, $assignment->scope_id)][] = $assignment->role_id;
                });

            $this->assignments[$user->id] = $map;
        }

        return $this->assignments[$user->id];
    }

    /**
     * @param  list<int>  $roleIds
     */
    private function assignedRolesHave(array $roleIds, string $permission): bool
    {
        foreach ($roleIds as $roleId) {
            if (in_array($permission, $this->rolePermissions()[$roleId] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, list<string>>
     */
    private function rolePermissions(): array
    {
        if ($this->rolePermissions === null) {
            $map = [];
            DB::table('permission_role')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->get(['permission_role.role_id', 'permissions.key'])
                ->each(function (object $row) use (&$map) {
                    /** @var object{role_id: int|string, key: string} $row */
                    $map[(int) $row->role_id][] = (string) $row->key;
                });

            $this->rolePermissions = $map;
        }

        return $this->rolePermissions;
    }

    /**
     * @return list<string>
     */
    private function capabilitiesFor(User $user): array
    {
        if (! isset($this->capabilities[$user->id])) {
            $this->capabilities[$user->id] = $user->role_id !== null ? ($this->rolePermissions()[$user->role_id] ?? []) : [];
        }

        return $this->capabilities[$user->id];
    }

    /**
     * @return array{repository: array<int, array{0: ?int, 1: int}>, module: array<int, array{0: ?int, 1: int}>, application: array<int, int>}
     */
    private function graph(): array
    {
        if ($this->graph === null) {
            $map = fn ($rows) => $rows->mapWithKeys(fn ($row) => [
                (int) $row->id => [$row->application_id !== null ? (int) $row->application_id : null, (int) $row->product_id],
            ])->all();

            $this->graph = [
                'repository' => $map(Repository::query()->get(['id', 'application_id', 'product_id'])),
                'module' => $map(Module::query()->get(['id', 'application_id', 'product_id'])),
                'application' => Application::query()->pluck('product_id', 'id')->map(fn ($id) => (int) $id)->all(),
            ];
        }

        return $this->graph;
    }
}
