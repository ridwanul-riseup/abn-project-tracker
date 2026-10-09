<?php

namespace App\Models;

use App\Support\Access\HasAccessScope;
use App\Support\Access\ScopedByAccess;
use App\Support\Archivable;
use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $product_id
 * @property int|null $application_id
 * @property int|null $layer_id
 * @property string|null $url
 * @property string|null $primary_owner
 * @property string|null $tech_stack
 * @property string|null $module_label
 * @property int|null $database_reference_id
 * @property string|null $api_reference
 * @property string|null $figma_url
 * @property string|null $issue_tracker_url
 * @property string|null $issue_tracker_ref
 * @property string|null $current_branch
 * @property int|null $deployment_target_id
 * @property int|null $status_id
 * @property string|null $notes
 * @property string|null $sensitive_notes
 * @property-read Product $product
 * @property-read Application|null $application
 */
class Repository extends Model implements HasAccessScope
{
    use Archivable, Auditable, ScopedByAccess;

    protected $guarded = ['id'];

    /** @var list<string> */
    protected array $auditRedact = ['sensitive_notes'];

    /**
     * @return array<string, string>
     */
    public static function accessScopeColumns(): array
    {
        return ['product_id' => 'product', 'application_id' => 'application'];
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
     * @return BelongsTo<Option, $this>
     */
    public function layer(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'layer_id');
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
    public function deploymentTarget(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'deployment_target_id');
    }

    /**
     * @return BelongsTo<DatabaseReference, $this>
     */
    public function databaseReference(): BelongsTo
    {
        return $this->belongsTo(DatabaseReference::class);
    }

    /**
     * @return BelongsToMany<Environment, $this>
     */
    public function environments(): BelongsToMany
    {
        return $this->belongsToMany(Environment::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsToMany<Integration, $this>
     */
    public function integrations(): BelongsToMany
    {
        return $this->belongsToMany(Integration::class);
    }

    /**
     * @return HasMany<Module, $this>
     */
    public function primaryModules(): HasMany
    {
        return $this->hasMany(Module::class, 'primary_repository_id');
    }

    /**
     * @return BelongsToMany<Module, $this>
     */
    public function relatedModules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<Deployment, $this>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function urlIsLink(): bool
    {
        return $this->url !== null && str_starts_with($this->url, 'https://');
    }

    public function auditLabel(): string
    {
        return $this->code.' — '.$this->name;
    }
}
