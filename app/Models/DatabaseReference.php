<?php

namespace App\Models;

use App\Support\Access\HasAccessScope;
use App\Support\Access\ScopedByAccess;
use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Database name and ownership reference (never credentials).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $product_id
 * @property-read Product|null $product
 */
class DatabaseReference extends Model implements HasAccessScope
{
    use Auditable, ScopedByAccess;

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

    public function auditLabel(): string
    {
        return $this->code.' — '.$this->name;
    }
}
