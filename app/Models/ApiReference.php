<?php

namespace App\Models;

use App\Support\Access\HasAccessScope;
use App\Support\Access\ScopedByAccess;
use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $product_id
 * @property int|null $repository_id
 * @property string|null $base_path
 * @property string|null $spec_url
 * @property-read Product $product
 * @property-read Repository|null $repository
 */
class ApiReference extends Model implements HasAccessScope
{
    use Auditable, ScopedByAccess;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    public static function accessScopeColumns(): array
    {
        return ['product_id' => 'product', 'repository_id' => 'repository'];
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

    public function auditLabel(): string
    {
        return $this->code.' — '.$this->name;
    }
}
