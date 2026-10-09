<?php

namespace App\Models;

use App\Support\Access\HasAccessScope;
use App\Support\Access\ScopedByAccess;
use App\Support\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * An item flagged "Needs Clarification" — missing, ambiguous or unmappable
 * information. The original value is preserved for later review.
 *
 * @property int $id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $product_id
 * @property string|null $field
 * @property string $issue
 * @property string|null $original_value
 * @property string|null $source
 * @property string $status
 * @property string|null $resolution
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property-read Model|null $subject
 * @property-read User|null $resolver
 */
class Clarification extends Model implements HasAccessScope
{
    use Auditable, ScopedByAccess;

    public const OPEN = 'open';

    public const RESOLVED = 'resolved';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /**
     * @return array<string, string>
     */
    public static function accessScopeColumns(): array
    {
        return [
            'product_id' => 'product',
            'application_id' => 'application',
            'repository_id' => 'repository',
            'module_id' => 'module',
        ];
    }

    /**
     * @param  Builder<Clarification>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::OPEN);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function auditLabel(): string
    {
        return 'Clarification #'.$this->id.($this->field ? ' ('.$this->field.')' : '');
    }
}
