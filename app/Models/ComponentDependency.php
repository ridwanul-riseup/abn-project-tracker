<?php

namespace App\Models;

use App\Support\Access\HasAccessScope;
use App\Support\Access\ScopedByAccess;
use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * "Source depends on target". Visible only when the user can read both ends.
 *
 * @property int $id
 * @property string $code
 * @property string $source_type
 * @property int $source_id
 * @property string $target_type
 * @property int $target_id
 * @property-read Model|null $source
 * @property-read Model|null $target
 */
class ComponentDependency extends Model implements HasAccessScope
{
    use Auditable, ScopedByAccess;

    /** Morph aliases allowed as dependency endpoints. */
    public const COMPONENT_TYPES = [
        'repository' => 'Repository',
        'module' => 'Module',
        'api' => 'API reference',
        'database' => 'Database',
        'integration' => 'Integration',
    ];

    protected $guarded = ['id'];

    /**
     * @return list<string>
     */
    public static function accessColumns(): array
    {
        return ['id', 'source_type', 'source_id', 'target_type', 'target_id'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Option, $this>
     */
    public function dependencyType(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'dependency_type_id');
    }

    /**
     * @return BelongsTo<Option, $this>
     */
    public function criticality(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'criticality_id');
    }

    /**
     * @return BelongsTo<Option, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'status_id');
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public static function componentLabel(?Model $component): string
    {
        if ($component === null) {
            return '—';
        }

        $code = $component->getAttribute('code');
        $name = $component->getAttribute('name');

        return trim($code.($name ? ' — '.$name : ''));
    }

    public function auditLabel(): string
    {
        return $this->code;
    }
}
