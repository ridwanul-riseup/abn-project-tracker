<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Login credentials for a specific application site URL. Passwords are encrypted at rest.
 *
 * @property int $id
 * @property int $application_site_url_id
 * @property string $title
 * @property string $account_login
 * @property string $password
 * @property string|null $short_description
 * @property int $sort_order
 * @property-read ApplicationSiteUrl $siteUrl
 */
class ApplicationSiteCredential extends Model
{
    use Auditable;

    /** @var list<string> */
    protected array $auditRedact = ['password'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['password' => 'encrypted'];
    }

    /**
     * @return BelongsTo<ApplicationSiteUrl, $this>
     */
    public function siteUrl(): BelongsTo
    {
        return $this->belongsTo(ApplicationSiteUrl::class, 'application_site_url_id');
    }

    public function auditLabel(): string
    {
        return $this->title.' ('.$this->account_login.')';
    }
}
