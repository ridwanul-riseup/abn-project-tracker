<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $subject_label
 * @property array<string, mixed>|null $changes
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /** Subject types whose activity is only shown on the audit page. */
    public const ADMIN_ONLY_TYPES = ['user', 'access_rule', 'option', 'role'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjectModel(): ?Model
    {
        if ($this->subject_type === null || $this->subject_id === null) {
            return null;
        }

        try {
            $class = Model::getActualClassNameForMorph($this->subject_type);
        } catch (\Throwable) {
            return null;
        }

        /** @var class-string<Model> $class */
        return class_exists($class) ? $class::query()->find($this->subject_id) : null;
    }
}
