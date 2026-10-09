<?php

namespace App\Models;

use App\Support\Auditable;
use App\Support\Options\Options;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configurable dropdown option. `value` is the stable internal key and
 * cannot be changed after creation; `label` is what users see.
 *
 * @property int $id
 * @property int $option_group_id
 * @property string $value
 * @property string $label
 * @property string|null $description
 * @property string|null $color
 * @property string|null $behavior
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_default
 * @property-read OptionGroup $group
 */
class Option extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_default' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        $flush = fn () => app(Options::class)->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * @return BelongsTo<OptionGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }

    public function filterLabel(): string
    {
        return $this->label.($this->is_active ? '' : ' (inactive)');
    }

    public function auditLabel(): string
    {
        return ($this->group->key ?? 'option').': '.$this->label.' ('.$this->value.')';
    }
}
