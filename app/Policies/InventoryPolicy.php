<?php

namespace App\Policies;

use App\Models\Repository;
use App\Models\User;
use App\Support\Access\AccessService;
use Illuminate\Database\Eloquent\Model;

/**
 * Products, applications, repositories, modules, dependencies, clarifications
 * and reference registries.
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
        return $this->access->hasCapability($user, 'inventory.write')
            && $this->access->canWrite($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }

    public function viewSensitive(User $user, Repository $repository): bool
    {
        return $this->access->hasCapability($user, 'repositories.view_sensitive')
            && $this->access->canRead($user, $repository);
    }
}
