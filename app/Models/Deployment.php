<?php

namespace App\Models;

use App\Support\Access\HasAccessScope;
use App\Support\Access\ScopedByAccess;
use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deployment record. `secret_reference` holds only a pointer to a secret
 * manager entry, never a secret value.
 *
 * @property int $id
 * @property string $code
 * @property int $repository_id
 * @property int|null $environment_id
 * @property-read Repository $repository
 */
class Deployment extends Model implements HasAccessScope
{
    use Auditable, ScopedByAccess;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['deployed_on' => 'date'];
    }

    /**
     * @return array<string, string>
     */
    public static function accessScopeColumns(): array
    {
        return ['repository_id' => 'repository'];
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * @return BelongsTo<Environment, $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * @return BelongsTo<Option, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'method_id');
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
        return $this->code;
    }
}
