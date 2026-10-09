<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Starting content for new Markdown documents, managed in Global Settings.
 * Placeholders: {{title}}, {{product}}, {{date}}.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property int|null $category_id
 * @property string $content
 * @property int $sort_order
 * @property bool $is_active
 * @property-read Option|null $category
 */
class DocumentTemplate extends Model
{
    use Auditable;

    public const PLACEHOLDERS = ['{{title}}', '{{product}}', '{{date}}'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return BelongsTo<Option, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'category_id');
    }

    public function auditLabel(): string
    {
        return $this->name.' ('.$this->key.')';
    }
}
