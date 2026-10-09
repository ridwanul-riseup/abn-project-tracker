<?php

namespace App\Models;

use App\Support\Access\HasAccessScope;
use App\Support\Access\ScopedByAccess;
use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Ecosystem-wide environment (Local, DEV, QA, ...). Readable by all active users;
 * editing requires application-wide write access.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $purpose
 */
class Environment extends Model implements HasAccessScope
{
    use Auditable, ScopedByAccess;

    protected $guarded = ['id'];

    public function openToAllUsers(): bool
    {
        return true;
    }

    /**
     * @return BelongsTo<Option, $this>
     */
    public function deploymentMethod(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'deployment_method_id');
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
