<?php

namespace App\Support\Access;

use App\Models\AccessRule;
use App\Models\Application;
use App\Models\ComponentDependency;
use App\Models\Module;
use App\Models\Repository;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
 * Role capabilities (permissions) are checked separately and limit which
 * actions a user may take inside the scopes they can reach.
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

    public function flush(): void
    {
        $this->rules = [];
        $this->capabilities = [];
        $this->graph = null;
        $this->visible = [];
    }

    public function isSuperAdmin(User $user): bool
    {
        return $user->is_active && $user->role?->key === Role::SUPER_ADMIN;
    }

    public function hasCapability(User $user, string $permission): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return in_array($permission, $this->capabilitiesFor($user), true);
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

    public function canRead(User $user, ?Model $resource): bool
    {
        return $resource !== null && $this->level($user, $resource) !== null;
    }

    public function canWrite(User $user, ?Model $resource): bool
    {
        return $resource !== null && $this->level($user, $resource) === self::WRITE;
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
                ->filter(fn (Model $model) => $this->level($user, $model) !== null)
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

            AccessRule::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->get(['scope_type', 'scope_id', 'permission'])
                ->each(function (AccessRule $rule) use (&$map, $strength) {
                    $key = $rule->scope_type === 'global' ? 'global' : $rule->scope_type.':'.$rule->scope_id;
                    $current = $map[$key] ?? null;
                    if ($current === null || $strength[$rule->permission] > $strength[$current]) {
                        $map[$key] = $rule->permission;
                    }
                });

            $this->rules[$user->id] = $map;
        }

        return $this->rules[$user->id];
    }

    /**
     * @return list<string>
     */
    private function capabilitiesFor(User $user): array
    {
        if (! isset($this->capabilities[$user->id])) {
            $this->capabilities[$user->id] = $user->role
                ? array_values(array_map('strval', $user->role->permissions()->pluck('key')->all()))
                : [];
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
