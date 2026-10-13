@php
    /** @var bool $canManageSiteCredentials */
    /** @var \Illuminate\Support\Collection<int, \App\Models\ApplicationSiteUrl>|array<int, array<string, mixed>> $siteUrls */
    $rows = collect(old('site_urls', $siteUrls instanceof \Illuminate\Support\Collection ? $siteUrls->map(fn ($u) => [
        'id' => $u->id,
        'label' => $u->label,
        'url' => $u->url,
        'credentials' => $u->credentials->map(fn ($c) => [
            'id' => $c->id,
            'title' => $c->title,
            'account_login' => $c->account_login,
            'short_description' => $c->short_description,
        ])->all(),
    ])->all() : $siteUrls))->values();
@endphp
@if ($canManageSiteCredentials)
    <div class="card mb-3" id="live-site-urls" data-nested-site-urls>
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Live site URLs</span>
            <button type="button" class="btn btn-sm btn-outline-primary" data-add-site-url><i class="bi bi-plus-lg"></i> Add site URL</button>
        </div>
        <div class="card-body">
            <p class="small text-muted">Register live environments for this application. Credentials are encrypted at rest and never shown in lists as plain text.</p>
            @error('site_urls')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
            <div data-site-url-list>
                @foreach ($rows as $urlIndex => $urlRow)
                    @include('applications.partials.site-url-row', ['urlIndex' => $urlIndex, 'urlRow' => $urlRow])
                @endforeach
            </div>
            <p class="small text-muted mb-0">Removing a URL deletes its credentials when you save.</p>
        </div>
    </div>

    <template id="hub-site-url-template">
        @include('applications.partials.site-url-row', ['urlIndex' => '__URL__', 'urlRow' => ['credentials' => []]])
    </template>
    <template id="hub-credential-template">
        @include('applications.partials.credential-row', ['urlIndex' => '__URL__', 'credIndex' => '__CRED__', 'credRow' => []])
    </template>
@endif
