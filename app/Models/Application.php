<?php

namespace App\Models;

use App\Support\Access\HasAccessScope;
use App\Support\Access\ScopedByAccess;
use App\Support\Archivable;
use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An application (e.g. "OTT Admin Panel") belonging to a product.
 *
 * @property int $id
 * @property string $code
 * @property int $product_id
 * @property string $name
 * @property int|null $application_type_id
 * @property int|null $classification_id
 * @property string|null $description
 * @property int|null $status_id
 * @property string|null $notes
 * @property-read Product $product
 */
class Application extends Model implements HasAccessScope
{
    use Archivable, Auditable, ScopedByAccess;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    public static function accessScopeColumns(): array
    {
        return ['product_id' => 'product'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Option, $this>
     */
    public function applicationType(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'application_type_id');
    }

    /**
     * @return BelongsTo<Option, $this>
     */
    public function classification(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'classification_id');
    }

    /**
     * @return BelongsTo<Option, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'status_id');
    }

    /**
     * @return HasMany<Repository, $this>
     */
    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class);
    }

    /**
     * @return HasMany<Module, $this>
     */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }

    /**
     * @return HasMany<ApplicationSiteUrl, $this>
     */
    public function siteUrls(): HasMany
    {
        return $this->hasMany(ApplicationSiteUrl::class)->orderBy('sort_order')->orderBy('id');
    }

    public function auditLabel(): string
    {
        return $this->code.' — '.$this->name;
    }
}
