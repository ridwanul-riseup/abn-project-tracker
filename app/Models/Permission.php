<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Application capability. Keys are defined in code (App\Support\Access\Capabilities)
 * because authorization checks depend on them.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $group
 * @property string|null $description
 */
class Permission extends Model
{
    protected $guarded = ['id'];

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
