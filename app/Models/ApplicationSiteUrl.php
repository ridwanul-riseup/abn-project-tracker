<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A live site URL registered against an application (dev/stage/prod).
 *
 * @property int $id
 * @property int $application_id
 * @property string|null $label
 * @property string $url
 * @property int $sort_order
 * @property-read Application $application
 */
class ApplicationSiteUrl extends Model
{
    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return HasMany<ApplicationSiteCredential, $this>
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(ApplicationSiteCredential::class)->orderBy('sort_order')->orderBy('id');
    }
}
