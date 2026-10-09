<?php

namespace App\Support\Access;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Default HasAccessScope implementation driven by foreign-key columns.
 *
 * Models using this trait define accessScopeColumns() mapping a column
 * (e.g. product_id) to a scope type (e.g. product).
 */
trait ScopedByAccess
{
    public static function bootScopedByAccess(): void
    {
        $flush = fn () => app(AccessService::class)->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * @return array<string, string>
     */
    public static function accessScopeColumns(): array
    {
        return [];
    }

    public function accessParents(): array
    {
        $parents = [];
        foreach (static::accessScopeColumns() as $column => $type) {
            $id = $this->getAttribute($column);
            if ($id !== null) {
                $parents[] = [$type, (int) $id];
            }
        }

        return $parents;
    }

    public static function accessColumns(): array
    {
        return array_values(array_unique(['id', ...array_keys(static::accessScopeColumns())]));
    }

    public function openToAllUsers(): bool
    {
        return false;
    }

    /**
     * Restrict a query to records the user may read.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $ids = app(AccessService::class)->visibleIds($user, static::class);

        if ($ids !== null) {
            $query->whereIn($this->getTable().'.id', $ids);
        }
    }
}
