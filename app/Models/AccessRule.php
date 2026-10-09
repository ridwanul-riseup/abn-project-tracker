<?php

namespace App\Models;

use App\Support\Access\AccessService;
use App\Support\Access\DescribesScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Scoped access grant/denial. Revoked rules are kept (revoked_at) for history.
 *
 * @property int $id
 * @property int $user_id
 * @property string $scope_type
 * @property int|null $scope_id
 * @property string $permission
 * @property string|null $reason
 * @property int|null $granted_by
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 * @property Carbon|null $created_at
 * @property-read User $user
 * @property-read User|null $granter
 * @property-read User|null $revoker
 */
class AccessRule extends Model
{
    use DescribesScope;

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
     * @param  Builder<AccessRule>  $query
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
        return $this->permission.' on '.$this->scopeLabel();
    }
}
