<x-layouts.app :title="$product->code" :breadcrumbs="['Products' => route('products.index'), $product->code => null]">
    <x-page-header :title="$product->code.' — '.$product->name" :subtitle="$product->description">
        <x-slot:actions>
            @can('docs.create-in', $product)
                <a href="{{ route('docs.create', ['product_id' => $product->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-journal-plus"></i> New document</a>
            @endcan
            @can('tasks.create-in', $product)
                <a href="{{ route('tasks.create', ['product_id' => $product->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-square"></i> New task</a>
            @endcan
            @can('update', $product)
                <a href="{{ route('products.edit', $product) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                @if ($product->isArchived())
                    <form method="POST" action="{{ route('products.restore', $product) }}">@csrf<button class="btn btn-outline-success btn-sm">Restore</button></form>
                @else
                    <form method="POST" action="{{ route('products.archive', $product) }}" data-confirm="Archive this product? It will be hidden from default lists.">@csrf<button class="btn btn-outline-danger btn-sm">Archive</button></form>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($product->isArchived())<div class="alert alert-dark py-2">This product is archived.</div>@endif
    <x-needs-clarification :items="$clarifications" />

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="text-muted small">Applications</div><div class="stat-value">{{ $applications->count() }}</div></div></div></div>
        <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="text-muted small">Repositories</div><div class="stat-value">{{ $repositories->count() }}</div></div></div></div>
        <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="text-muted small">Modules</div><div class="stat-value">{{ $modules->count() }}</div></div></div></div>
        <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="text-muted small">Open tasks</div><div class="stat-value"><a href="{{ route('tasks.index', ['product' => $product->id]) }}">{{ $openTasks }}</a></div></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">Details</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Product ID</dt><dd class="col-7">{{ $product->code }}</dd>
                        <dt class="col-5">Status</dt><dd class="col-7"><x-option-badge :option="$product->status" /></dd>
                        <dt class="col-5">Primary owner</dt><dd class="col-7">{{ $product->primary_owner ?? '—' }}</dd>
                        <dt class="col-5">Database(s)</dt>
                        <dd class="col-7">
                            @forelse ($databases as $db)
                                <a href="{{ route('registries.show', ['databases', $db->id]) }}">{{ $db->code }}</a> <span class="text-muted">({{ $db->name }})</span><br>
                            @empty — @endforelse
                        </dd>
                        <dt class="col-5">Dependencies</dt><dd class="col-7"><a href="{{ route('dependencies.index', ['product' => $product->id]) }}">{{ $dependencyCount }} recorded</a></dd>
                        <dt class="col-5">Docs folder</dt><dd class="col-7"><code>products/{{ $product->slug }}/</code></dd>
                    </dl>
                    @if ($product->notes)
                        <hr><div class="small text-pre-wrap">{!! nl2br(e($product->notes)) !!}</div>
                    @endif
                </div>
            </div>
            <div class="card">
                <div class="card-header">Documentation</div>
                <ul class="list-group list-group-flush">
                    @forelse ($documents as $doc)
                        <li class="list-group-item small"><a href="{{ route('docs.show', $doc) }}">{{ $doc->title }}</a> <x-option-badge :option="$doc->category" class="ms-1" /></li>
                    @empty
                        <li class="list-group-item"><x-empty message="No documents yet." icon="bi-journal" /></li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">Applications</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>ID</th><th>Application</th><th>Type</th><th>FE/BE</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($applications as $app)
                        <tr><td><a href="{{ route('applications.show', $app) }}">{{ $app->code }}</a></td><td>{{ $app->name }}</td><td>{{ $app->applicationType?->label ?? '—' }}</td><td>{{ $app->classification?->label ?? '—' }}</td><td><x-option-badge :option="$app->status" /></td></tr>
                    @empty
                        <tr><td colspan="5"><x-empty message="No applications." /></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
            <div class="card mb-3">
                <div class="card-header">Repositories</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>ID</th><th>Repository</th><th>Application</th><th>Layer</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($repositories as $repo)
                        <tr><td><a href="{{ route('repositories.show', $repo) }}">{{ $repo->code }}</a></td><td class="text-break-all">{{ $repo->name }}</td><td>{{ $repo->application?->name ?? '—' }}</td><td><x-option-badge :option="$repo->layer" /></td><td><x-option-badge :option="$repo->status" /></td></tr>
                    @empty
                        <tr><td colspan="5"><x-empty message="No repositories." /></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
            <div class="card">
                <div class="card-header">Modules</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>ID</th><th>Module</th><th>Primary repository</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($modules as $module)
                        <tr><td><a href="{{ route('modules.show', $module) }}">{{ $module->code }}</a></td><td>{{ $module->name }}</td><td><x-ref :model="$module->primaryRepository" /></td><td><x-option-badge :option="$module->status" /></td></tr>
                    @empty
                        <tr><td colspan="4"><x-empty message="No modules." /></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
</x-layouts.app>
