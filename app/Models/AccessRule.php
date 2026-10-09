<?php

namespace App\Models;

use App\Support\Access\AccessService;
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

    /**
     * The scoped record, or null for application-wide rules.
     */
    public function scopeModel(): ?Model
    {
        if ($this->scope_type === 'global' || $this->scope_id === null) {
            return null;
        }

        $class = Model::getActualClassNameForMorph($this->scope_type);

        /** @var class-string<Model> $class */
        return $class::query()->find($this->scope_id);
    }

    public function scopeLabel(): string
    {
        if ($this->scope_type === 'global') {
            return 'Application-wide';
        }

        $model = $this->scopeModel();
        if ($model === null) {
            return ucfirst($this->scope_type).' #'.$this->scope_id.' (deleted)';
        }

        $label = $model->getAttribute('code') ?? $model->getAttribute('path');
        $name = $model->getAttribute('name') ?? $model->getAttribute('title');

        return ucfirst($this->scope_type).': '.trim($label.' — '.$name, ' —');
    }

    public function auditLabel(): string
    {
        return $this->permission.' on '.$this->scopeLabel();
    }
}
