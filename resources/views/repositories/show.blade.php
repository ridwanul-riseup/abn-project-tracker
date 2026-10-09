@php use App\Models\ComponentDependency; @endphp
<x-layouts.app :title="$repository->code" :breadcrumbs="['Repositories' => route('repositories.index'), $repository->code => null]">
    <x-page-header :title="$repository->code" :subtitle="$repository->name">
        <x-slot:actions>
            @can('create-in', ['docs.create', $repository])
                <a href="{{ route('docs.create', ['product_id' => $repository->product_id, 'repository_id' => $repository->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-journal-plus"></i> New document</a>
            @endcan
            @can('create-in', ['tasks.create', $repository])
                <a href="{{ route('tasks.create', ['product_id' => $repository->product_id, 'repository_id' => $repository->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-square"></i> New task</a>
            @endcan
            @can('update', $repository)
                <a href="{{ route('dependencies.create', ['source' => 'repository:'.$repository->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-diagram-3"></i> Add dependency</a>
                <a href="{{ route('repositories.edit', $repository) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
            @endcan
            @can('delete', $repository)
                @if ($repository->isArchived())
                    <form method="POST" action="{{ route('repositories.restore', $repository) }}">@csrf<button class="btn btn-outline-success btn-sm">Restore</button></form>
                @else
                    <form method="POST" action="{{ route('repositories.archive', $repository) }}" data-confirm="Archive this repository?">@csrf<button class="btn btn-outline-danger btn-sm">Archive</button></form>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>
    @if ($repository->isArchived())<div class="alert alert-dark py-2">This repository is archived.</div>@endif
    <x-needs-clarification :items="$clarifications" />

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">Details</div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5">Repository URL</dt>
                        <dd class="col-7">
                            @if ($repository->url)
                                <x-ext-link :url="$repository->url" :copy="true" />
                            @else
                                <span class="badge text-bg-warning"><i class="bi bi-question-circle"></i> Needs Clarification</span>
                            @endif
                        </dd>
                        <dt class="col-5">Product</dt><dd class="col-7"><x-ref :model="$repository->product" :name="true" /></dd>
                        <dt class="col-5">Application</dt><dd class="col-7"><x-ref :model="$repository->application" :name="true" /></dd>
                        <dt class="col-5">Layer</dt><dd class="col-7"><x-option-badge :option="$repository->layer" /></dd>
                        <dt class="col-5">Status</dt><dd class="col-7"><x-option-badge :option="$repository->status" /></dd>
                        <dt class="col-5">Primary owner</dt><dd class="col-7">{{ $repository->primary_owner ?? '—' }}</dd>
                        <dt class="col-5">Technology stack</dt><dd class="col-7">{{ $repository->tech_stack ?? '—' }}</dd>
                        <dt class="col-5">Primary module</dt><dd class="col-7">{{ $repository->module_label ?? '—' }}</dd>
                        <dt class="col-5">Database</dt><dd class="col-7"><x-ref :model="$repository->databaseReference" :name="true" /></dd>
                        <dt class="col-5">API reference</dt><dd class="col-7"><code>{{ $repository->api_reference ?? '—' }}</code></dd>
                        <dt class="col-5">Figma</dt><dd class="col-7"><x-ext-link :url="$repository->figma_url" label="Open in Figma" :copy="true" /></dd>
                        <dt class="col-5">Issue tracker</dt>
                        <dd class="col-7">
                            <x-ext-link :url="$repository->issue_tracker_url" />
                            @if ($repository->issue_tracker_ref)<div><code>{{ $repository->issue_tracker_ref }}</code></div>@endif
                        </dd>
                        <dt class="col-5">Current branch</dt><dd class="col-7">{{ $repository->current_branch ?? '—' }}</dd>
                        <dt class="col-5">Deployment target</dt><dd class="col-7"><x-option-badge :option="$repository->deploymentTarget" /></dd>
                        <dt class="col-5">Environments</dt>
                        <dd class="col-7">
                            @forelse ($repository->environments as $env)
                                <a class="badge text-bg-light border text-decoration-none" href="{{ route('registries.show', ['environments', $env->id]) }}">{{ $env->name }}</a>
                            @empty — @endforelse
                        </dd>
                        <dt class="col-5">Open tasks</dt><dd class="col-7"><a href="{{ route('tasks.index', ['repository_id' => $repository->id]) }}">{{ $openTasks }}</a></dd>
                    </dl>
                    @if ($repository->notes)
                        <hr><div class="small">{!! nl2br(e($repository->notes)) !!}</div>
                    @endif
                </div>
            </div>
            @if ($canSeeSensitive)
                <div class="card mb-3 border-warning">
                    <div class="card-header bg-warning-subtle"><i class="bi bi-shield-lock"></i> Sensitive technical notes</div>
                    <div class="card-body small">{!! $repository->sensitive_notes ? nl2br(e($repository->sensitive_notes)) : '<span class="text-muted">None recorded.</span>' !!}</div>
                </div>
            @endif
        </div>

        <div class="col-lg-7">
            <div class="card mb-3" id="depends_on">
                <div class="card-header">Dependencies — this repository depends on</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>ID</th><th>Target</th><th>Type</th><th>Criticality</th><th>Failure impact</th></tr></thead>
                    <tbody>
                    @forelse ($dependsOn as $dep)
                        <tr>
                            <td><a href="{{ route('dependencies.edit', $dep) }}">{{ $dep->code }}</a></td>
                            <td><span class="text-muted small">{{ ComponentDependency::COMPONENT_TYPES[$dep->target_type] ?? $dep->target_type }}</span><br><x-ref :model="$dep->target" :name="true" /></td>
                            <td><x-option-badge :option="$dep->dependencyType" /></td>
                            <td><x-option-badge :option="$dep->criticality" /></td>
                            <td class="small">{{ $dep->failure_impact ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty message="No outgoing dependencies recorded." /></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <x-list-pager :items="$dependsOn" />
            </div>
            <div class="card mb-3" id="used_by">
                <div class="card-header">Used by — components that depend on this repository</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>ID</th><th>Source</th><th>Type</th><th>Criticality</th><th>Failure impact</th></tr></thead>
                    <tbody>
                    @forelse ($usedBy as $dep)
                        <tr>
                            <td><a href="{{ route('dependencies.edit', $dep) }}">{{ $dep->code }}</a></td>
                            <td><x-ref :model="$dep->source" :name="true" /></td>
                            <td><x-option-badge :option="$dep->dependencyType" /></td>
                            <td><x-option-badge :option="$dep->criticality" /></td>
                            <td class="small">{{ $dep->failure_impact ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty message="Nothing depends on this repository (as far as recorded)." /></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <x-list-pager :items="$usedBy" />
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card h-100" id="documents">
                        <div class="card-header">Related documents</div>
                        <ul class="list-group list-group-flush small">
                            @forelse ($documents as $doc)
                                <li class="list-group-item"><a href="{{ route('docs.show', $doc) }}">{{ $doc->title }}</a></li>
                            @empty
                                <li class="list-group-item text-muted">No documents linked.</li>
                            @endforelse
                        </ul>
                        <x-list-pager :items="$documents" />
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100" id="modules">
                        <div class="card-header">Modules</div>
                        <ul class="list-group list-group-flush small">
                            @forelse ($modules as $module)
                                <li class="list-group-item"><a href="{{ route('modules.show', $module) }}">{{ $module->code }}</a> — {{ $module->name }}</li>
                            @empty
                                <li class="list-group-item text-muted">No modules reference this repository.</li>
                            @endforelse
                        </ul>
                        <x-list-pager :items="$modules" />
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100" id="siblings">
                        <div class="card-header">Related repositories (same application)</div>
                        <ul class="list-group list-group-flush small">
                            @forelse ($siblings as $sibling)
                                <li class="list-group-item"><a href="{{ route('repositories.show', $sibling) }}">{{ $sibling->code }}</a> — <span class="text-break-all">{{ $sibling->name }}</span></li>
                            @empty
                                <li class="list-group-item text-muted">None.</li>
                            @endforelse
                        </ul>
                        <x-list-pager :items="$siblings" />
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">APIs, Figma &amp; integrations</div>
                        <ul class="list-group list-group-flush small">
                            @foreach ($apis as $api)
                                <li class="list-group-item"><i class="bi bi-plug"></i> <x-ref :model="$api" /> <code>{{ $api->base_path }}</code></li>
                            @endforeach
                            @foreach ($figma as $fig)
                                <li class="list-group-item"><i class="bi bi-palette"></i> <x-ref :model="$fig" :name="true" /></li>
                            @endforeach
                            @foreach ($integrations as $int)
                                <li class="list-group-item"><i class="bi bi-arrow-left-right"></i> <x-ref :model="$int" /></li>
                            @endforeach
                            @if ($apis->isEmpty() && $figma->isEmpty() && $integrations->isEmpty())
                                <li class="list-group-item text-muted">None recorded.</li>
                            @endif
                        </ul>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card" id="deployments">
                        <div class="card-header">Deployments</div>
                        <div class="table-responsive"><table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>ID</th><th>Environment</th><th>Branch</th><th>Version</th><th>Status</th></tr></thead>
                            <tbody>
                            @forelse ($deployments as $deployment)
                                <tr><td><x-ref :model="$deployment" /></td><td>{{ $deployment->environment?->name ?? '—' }}</td><td><code>{{ $deployment->branch ?? '—' }}</code></td><td>{{ $deployment->version ?? '—' }}</td><td><x-option-badge :option="$deployment->status" /></td></tr>
                            @empty
                                <tr><td colspan="5" class="text-muted small">No deployment records.</td></tr>
                            @endforelse
                            </tbody>
                        </table></div>
                        <x-list-pager :items="$deployments" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
