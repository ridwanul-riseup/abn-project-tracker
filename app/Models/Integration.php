<?php

namespace App\Models;

use App\Support\Access\HasAccessScope;
use App\Support\Access\ScopedByAccess;
use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Cross-product integration (e.g. SHR-OTT-INT-001). Visible to users who can
 * read either involved product (unless denied on one of them).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $source_product_id
 * @property int|null $target_product_id
 */
class Integration extends Model implements HasAccessScope
{
    use Auditable, ScopedByAccess;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    public static function accessScopeColumns(): array
    {
        return ['source_product_id' => 'product', 'target_product_id' => 'product'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function sourceProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'source_product_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function targetProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'target_product_id');
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<Option, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'status_id');
    }

    /**
     * @return BelongsToMany<Repository, $this>
     */
    public function repositories(): BelongsToMany
    {
        return $this->belongsToMany(Repository::class);
    }

    public function auditLabel(): string
    {
        return $this->code.' — '.$this->name;
    }
}
