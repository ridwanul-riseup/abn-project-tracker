<?php

namespace App\Models;

use App\Support\Access\HasAccessScope;
use App\Support\Access\ScopedByAccess;
use App\Support\Archivable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Metadata for a Markdown file. The content itself lives in the .md file at `path`
 * (relative to the managed docs directory).
 *
 * @property int $id
 * @property string $path
 * @property string $title
 * @property int|null $category_id
 * @property int|null $product_id
 * @property int|null $repository_id
 * @property int|null $module_id
 * @property string $visibility
 * @property string|null $summary
 * @property int|null $owner_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property string|null $content_hash
 * @property int $size_bytes
 * @property CarbonInterface|null $content_updated_at
 * @property CarbonInterface|null $archived_at
 * @property CarbonInterface|null $updated_at
 * @property-read Product|null $product
 * @property-read Repository|null $repository
 * @property-read Module|null $module
 * @property-read Option|null $category
 */
class Document extends Model implements HasAccessScope
{
    // Not Auditable: DocumentService records explicit audit entries with revision context.
    use Archivable, ScopedByAccess;

    public const VISIBILITY_INHERIT = 'inherit';

    public const VISIBILITY_ALL_USERS = 'all_users';

    public const VISIBILITIES = [
        self::VISIBILITY_INHERIT => 'Restricted — inherits product/repository/module access',
        self::VISIBILITY_ALL_USERS => 'All active users (unless explicitly denied)',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['content_updated_at' => 'datetime'];
    }

    /**
     * @return array<string, string>
     */
    public static function accessScopeColumns(): array
    {
        return ['product_id' => 'product', 'repository_id' => 'repository', 'module_id' => 'module'];
    }

    /**
     * @return list<string>
     */
    public static function accessColumns(): array
    {
        return ['id', 'product_id', 'repository_id', 'module_id', 'visibility'];
    }

    public function openToAllUsers(): bool
    {
        return $this->visibility === self::VISIBILITY_ALL_USERS;
    }

    /**
     * @return BelongsTo<Option, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'category_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return HasMany<DocumentRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class)->latest('id');
    }

    /**
     * @return BelongsToMany<Task, $this>
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }

    public function auditLabel(): string
    {
        return $this->title.' ('.$this->path.')';
    }
}
