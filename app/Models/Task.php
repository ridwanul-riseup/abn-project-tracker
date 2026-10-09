<?php

namespace App\Models;

use App\Support\Access\HasAccessScope;
use App\Support\Access\ScopedByAccess;
use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property int $number
 * @property string $title
 * @property string|null $description
 * @property int $product_id
 * @property int|null $application_id
 * @property int|null $repository_id
 * @property int|null $module_id
 * @property int $type_id
 * @property int $status_id
 * @property int $priority_id
 * @property int|null $assignee_id
 * @property int|null $reporter_id
 * @property Carbon|null $start_date
 * @property Carbon|null $due_date
 * @property string|null $issue_url
 * @property string|null $blocker_description
 * @property string|null $completion_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Option $status
 * @property-read Option $priority
 * @property-read Option $type
 * @property-read User|null $assignee
 */
class Task extends Model implements HasAccessScope
{
    use Auditable, ScopedByAccess;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'due_date' => 'date'];
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
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return BelongsTo<Option, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'type_id');
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
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return HasMany<TaskComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->oldest('id');
    }

    /**
     * @return BelongsToMany<Document, $this>
     */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && ! $this->due_date->isToday()
            && $this->status->behavior !== 'closed';
    }

    public function auditLabel(): string
    {
        return $this->key.' — '.$this->title;
    }
}
