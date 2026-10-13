<x-layouts.app :title="$application->code" :breadcrumbs="['Applications' => route('applications.index'), $application->code => null]">
    <x-page-header :title="$application->code.' — '.$application->name">
        <x-slot:actions>
            @can('update', $application)
                <a href="{{ route('repositories.create', ['product_id' => $application->product_id, 'application_id' => $application->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg"></i> Add repository</a>
                <a href="{{ route('applications.edit', $application) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
            @endcan
            @can('delete', $application)
                @if ($application->isArchived())
                    <form method="POST" action="{{ route('applications.restore', $application) }}">@csrf<button class="btn btn-outline-success btn-sm">Restore</button></form>
                @else
                    <form method="POST" action="{{ route('applications.archive', $application) }}" data-confirm="Archive this application?">@csrf<button class="btn btn-outline-danger btn-sm">Archive</button></form>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>
    <x-needs-clarification :items="$clarifications" />

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card"><div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5">Product</dt><dd class="col-7"><x-ref :model="$application->product" :name="true" /></dd>
                    <dt class="col-5">Type</dt><dd class="col-7">{{ $application->applicationType?->label ?? '—' }}</dd>
                    <dt class="col-5">Frontend/backend</dt><dd class="col-7">{{ $application->classification?->label ?? '—' }}</dd>
                    <dt class="col-5">Status</dt><dd class="col-7"><x-option-badge :option="$application->status" /></dd>
                </dl>
                @if ($application->description)<hr><p class="small mb-0">{!! nl2br(e($application->description)) !!}</p>@endif
                @if ($application->notes)<hr><div class="small text-muted">{!! nl2br(e($application->notes)) !!}</div>@endif
            </div></div>
        </div>
        <div class="col-lg-8">
            @if ($canViewCredentials)
                <div class="card mb-3" id="credentials">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Live site URLs &amp; credentials</span>
                        @can('update', $application)
                            <a href="{{ route('applications.edit', $application) }}#live-site-urls" class="btn btn-sm btn-outline-primary">Manage</a>
                        @endcan
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Environment</th>
                                    <th>Site URL</th>
                                    <th class="text-end">Credentials</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse ($siteUrls as $siteUrl)
                                <tr>
                                    <td class="small">{{ $siteUrl->label ?: '—' }}</td>
                                    <td class="small"><x-ext-link :url="$siteUrl->url" /></td>
                                    <td class="small text-end">
                                        @if ($siteUrl->credentials->isEmpty())
                                            <span class="text-muted">None</span>
                                        @else
                                            <button type="button" class="btn btn-sm btn-link p-0 align-baseline"
                                                    data-bs-toggle="modal" data-bs-target="#site-url-creds-{{ $siteUrl->id }}">
                                                View {{ $siteUrl->credentials->count() }} {{ str('credential')->plural($siteUrl->credentials->count()) }}
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3"><x-empty message="No site URLs or credentials registered yet." /></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <x-list-pager :items="$siteUrls" />
                    @foreach ($siteUrls as $siteUrl)
                        @if ($siteUrl->credentials->isNotEmpty())
                            @include('applications.partials.site-url-credentials-modal', ['siteUrl' => $siteUrl])
                        @endif
                    @endforeach
                </div>
            @endif
            <div class="card mb-3" id="repositories">
                <div class="card-header">Related repositories</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>ID</th><th>Repository</th><th>Layer</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($repositories as $repo)
                        <tr><td><a href="{{ route('repositories.show', $repo) }}">{{ $repo->code }}</a></td><td class="text-break-all">{{ $repo->name }}</td><td><x-option-badge :option="$repo->layer" /></td><td><x-option-badge :option="$repo->status" /></td></tr>
                    @empty
                        <tr><td colspan="4"><x-empty message="No repositories linked." /></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <x-list-pager :items="$repositories" />
            </div>
            <div class="card" id="modules">
                <div class="card-header">Modules</div>
                <ul class="list-group list-group-flush">
                    @forelse ($modules as $module)
                        <li class="list-group-item small"><a href="{{ route('modules.show', $module) }}">{{ $module->code }}</a> — {{ $module->name }} <x-option-badge :option="$module->status" /></li>
                    @empty
                        <li class="list-group-item"><x-empty message="No modules linked to this application." /></li>
                    @endforelse
                </ul>
                <x-list-pager :items="$modules" />
            </div>
        </div>
    </div>
</x-layouts.app>
