<?php

namespace App\Models;

use App\Support\Access\AccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property bool $is_system
 */
class Role extends Model
{
    public const SUPER_ADMIN = 'super_admin';

    public const PROJECT_ADMIN = 'project_admin';

    public const PROJECT_MANAGER = 'project_manager';

    public const DEVELOPER = 'developer';

    public const QA_ENGINEER = 'qa_engineer';

    public const VIEWER = 'viewer';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(AccessService::class)->flush());
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->key === self::SUPER_ADMIN;
    }

    public function auditLabel(): string
    {
        return $this->name;
    }
}
