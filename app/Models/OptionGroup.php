<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 */
class OptionGroup extends Model
{
    protected $guarded = ['id'];

    /**
     * @return HasMany<Option, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(Option::class)->orderBy('sort_order')->orderBy('label');
    }

    public function supportsBehavior(): bool
    {
        return $this->key === 'task_status';
    }
}
