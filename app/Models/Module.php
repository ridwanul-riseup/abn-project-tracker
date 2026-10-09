<?php

namespace App\Models;

use App\Support\Access\HasAccessScope;
use App\Support\Access\ScopedByAccess;
use App\Support\Archivable;
use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A functional module / feature area of a product (e.g. SHR-AUTH).
 *
 * @property int $id
 * @property string $code
 * @property int $product_id
 * @property int|null $application_id
 * @property string $name
 * @property string|null $description
 * @property int|null $primary_repository_id
 * @property string|null $api_reference
 * @property int|null $database_reference_id
 * @property string|null $figma_url
 * @property string|null $owner
 * @property int|null $status_id
 * @property int|null $priority_id
 * @property string|null $notes
 * @property-read Product $product
 * @property-read Application|null $application
 * @property-read Repository|null $primaryRepository
 */
class Module extends Model implements HasAccessScope
{
    use Archivable, Auditable, ScopedByAccess;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    public static function accessScopeColumns(): array
    {
        return ['product_id' => 'product', 'application_id' => 'application'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function primaryRepository(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'primary_repository_id');
    }

    /**
     * @return BelongsToMany<Repository, $this>
     */
    public function relatedRepositories(): BelongsToMany
    {
        return $this->belongsToMany(Repository::class);
    }

    /**
     * @return BelongsTo<DatabaseReference, $this>
     */
    public function databaseReference(): BelongsTo
    {
        return $this->belongsTo(DatabaseReference::class);
    }

    /**
     * @return BelongsTo<Option, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'status_id');
    }

    /**
     * @return BelongsTo<Option, $this>
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'priority_id');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function auditLabel(): string
    {
        return $this->code.' — '.$this->name;
    }
}
