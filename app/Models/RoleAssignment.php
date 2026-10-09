<?php

namespace App\Models;

use App\Support\Access\AccessService;
use App\Support\Access\DescribesScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A role given to a user on one scope (application-wide, product, application,
 * repository or module). The role's permissions apply inside that scope, and the
 * assignment grants read access there (write access when the role can change content).
 * Revoked assignments are kept (revoked_at) for history.
 *
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 * @property string $scope_type
 * @property int|null $scope_id
 * @property string|null $reason
 * @property int|null $granted_by
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 * @property Carbon|null $created_at
 * @property-read User $user
 * @property-read Role $role
 * @property-read User|null $granter
 * @property-read User|null $revoker
 */
class RoleAssignment extends Model
{
    use DescribesScope;

    public const SCOPE_TYPES = ['global', 'product', 'application', 'repository', 'module'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        $flush = fn () => app(AccessService::class)->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * @param  Builder<RoleAssignment>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function auditLabel(): string
    {
        return ($this->role->name ?? 'Role').' on '.$this->scopeLabel();
    }
}
