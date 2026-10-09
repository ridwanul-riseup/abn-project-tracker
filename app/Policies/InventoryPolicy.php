<?php

namespace App\Policies;

use App\Models\Repository;
use App\Models\User;
use App\Support\Access\AccessService;
use App\Support\Access\Capabilities;
use Illuminate\Database\Eloquent\Model;

/**
 * Products, applications, repositories, modules, dependencies, clarifications
 * and reference registries. The permission is the record's feature module
 * (e.g. repositories.update), checked on the record so scoped roles apply.
 */
class InventoryPolicy
{
    public function __construct(private readonly AccessService $access) {}

    public function view(User $user, Model $model): bool
    {
        return $this->access->canRead($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allows($user, $model, Capabilities::for($model, 'update') ?? Capabilities::for($model, 'resolve'));
    }

    /**
     * Archive (products, applications, repositories, modules) or delete (dependencies, references).
     */
    public function delete(User $user, Model $model): bool
    {
        return $this->allows($user, $model, Capabilities::for($model, 'archive') ?? Capabilities::for($model, 'delete'));
    }

    public function viewSensitive(User $user, Repository $repository): bool
    {
        return $this->access->can($user, 'repositories.view_sensitive', $repository)
            && $this->access->canRead($user, $repository);
    }

    private function allows(User $user, Model $model, ?string $permission): bool
    {
        return $permission !== null
            && $this->access->can($user, $permission, $model)
            && $this->access->canWrite($user, $model);
    }
}
