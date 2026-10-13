<?php

namespace App\Support\Applications;

use App\Models\Application;
use App\Models\ApplicationSiteCredential;
use App\Models\ApplicationSiteUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Persists nested site URL and credential rows from the application form.
 */
class SiteUrlSync
{
    public const MAX_URLS = 25;

    public const MAX_CREDENTIALS_PER_URL = 50;

    /**
     * @param  list<array<string, mixed>>  $siteUrls
     */
    public function sync(Application $application, array $siteUrls, bool $canManageCredentials): void
    {
        if (! $canManageCredentials) {
            if ($siteUrls !== []) {
                throw ValidationException::withMessages(['site_urls' => 'You may not manage site credentials for this application.']);
            }

            return;
        }

        if (count($siteUrls) > self::MAX_URLS) {
            throw ValidationException::withMessages(['site_urls' => 'At most '.self::MAX_URLS.' site URLs are allowed per application.']);
        }

        DB::transaction(function () use ($application, $siteUrls) {
            $existingUrlIds = $application->siteUrls()->pluck('id')->all();
            $keptUrlIds = [];

            foreach ($siteUrls as $urlIndex => $urlRow) {
                $url = $this->upsertUrl($application, $urlRow, $urlIndex);
                $keptUrlIds[] = $url->id;
                $credentialRows = $urlRow['credentials'] ?? [];
                $this->syncCredentials($url, is_array($credentialRows) ? array_values($credentialRows) : []);
            }

            $remove = array_diff($existingUrlIds, $keptUrlIds);
            if ($remove !== []) {
                ApplicationSiteUrl::query()->whereKey($remove)->delete();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertUrl(Application $application, array $row, int $sortOrder): ApplicationSiteUrl
    {
        $attributes = [
            'label' => blank($row['label'] ?? null) ? null : trim((string) $row['label']),
            'url' => trim((string) ($row['url'] ?? '')),
            'sort_order' => $sortOrder,
        ];

        $id = isset($row['id']) ? (int) $row['id'] : null;
        if ($id) {
            $url = ApplicationSiteUrl::query()->where('application_id', $application->id)->whereKey($id)->first();
            if ($url === null) {
                throw ValidationException::withMessages(['site_urls' => 'One of the site URLs does not belong to this application.']);
            }
            $url->update($attributes);

            return $url;
        }

        return $application->siteUrls()->create($attributes);
    }

    /**
     * @param  list<array<string, mixed>>  $credentialRows
     */
    private function syncCredentials(ApplicationSiteUrl $url, array $credentialRows): void
    {
        if (count($credentialRows) > self::MAX_CREDENTIALS_PER_URL) {
            throw ValidationException::withMessages(['site_urls' => 'At most '.self::MAX_CREDENTIALS_PER_URL.' credentials are allowed per site URL.']);
        }

        $existingIds = $url->credentials()->pluck('id')->all();
        $keptIds = [];

        foreach ($credentialRows as $credIndex => $row) {
            $attributes = [
                'title' => trim((string) ($row['title'] ?? '')),
                'account_login' => trim((string) ($row['account_login'] ?? '')),
                'short_description' => blank($row['short_description'] ?? null) ? null : trim((string) $row['short_description']),
                'sort_order' => $credIndex,
            ];

            $password = (string) ($row['password'] ?? '');
            $id = isset($row['id']) ? (int) $row['id'] : null;

            if ($id) {
                $credential = ApplicationSiteCredential::query()
                    ->where('application_site_url_id', $url->id)
                    ->whereKey($id)
                    ->firstOrFail();
                if ($password !== '') {
                    $attributes['password'] = $password;
                }
                $credential->update($attributes);
                $keptIds[] = $credential->id;

                continue;
            }

            if ($password === '') {
                throw ValidationException::withMessages(['site_urls' => 'A password is required for each new credential.']);
            }

            $attributes['password'] = $password;
            $keptIds[] = $url->credentials()->create($attributes)->id;
        }

        $remove = array_diff($existingIds, $keptIds);
        if ($remove !== []) {
            ApplicationSiteCredential::query()->whereKey($remove)->delete();
        }
    }
}
