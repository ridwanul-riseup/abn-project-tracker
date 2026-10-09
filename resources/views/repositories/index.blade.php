@php $opts = app(\App\Support\Options\Options::class); @endphp
<x-layouts.app title="Repositories" :breadcrumbs="['Repositories' => null]">
    <x-page-header title="Repository registry" subtitle="Repositories, their responsibilities and references. URLs are optional — some entries only have a name.">
        <x-slot:actions>
            @can('inventory.write')
                <a href="{{ route('repositories.create', ['product_id' => request('product')]) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Register repository</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form class="card card-body mb-3 py-2" method="GET">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Search ID, name, URL, stack, API"></div>
            <div class="col-md-3">
                <select name="product" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All products</option>
                    @foreach ($products as $id => $label)<option value="{{ $id }}" @selected(request('product') == $id)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="layer" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All layers</option>
                    @foreach ($opts->forFilter('repository_layer') as $o)<option value="{{ $o->id }}" @selected(request('layer') == $o->id)>{{ $o->filterLabel() }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All statuses</option>
                    @foreach ($opts->forFilter('repository_status') as $o)<option value="{{ $o->id }}" @selected(request('status') == $o->id)>{{ $o->filterLabel() }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-auto form-check ms-2">
                <input class="form-check-input" type="checkbox" name="missing_url" value="1" id="missing_url" @checked(request('missing_url'))>
                <label class="form-check-label small" for="missing_url">Missing URL</label>
            </div>
            <div class="col-md-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
        </div>
    </form>

    <div class="card"><div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light"><tr><th>ID</th><th>Repository</th><th>Product</th><th>Application</th><th>Layer</th><th>Module</th><th>API</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($repositories as $repo)
                <tr>
                    <td class="text-nowrap"><a class="fw-semibold" href="{{ route('repositories.show', $repo) }}">{{ $repo->code }}</a></td>
                    <td class="text-break-all">
                        {{ $repo->name }}
                        @if ($repo->isArchived())<span class="badge text-bg-dark">Archived</span>@endif
                        <div class="small">
                            @if ($repo->url)
                                <x-ext-link :url="$repo->url" label="Open repository" :copy="true" />
                            @else
                                <span class="badge text-bg-warning"><i class="bi bi-question-circle"></i> URL missing</span>
                            @endif
                        </div>
                    </td>
                    <td><x-ref :model="$repo->product" /></td>
                    <td>{{ $repo->application?->name ?? '—' }}</td>
                    <td><x-option-badge :option="$repo->layer" /></td>
                    <td class="small">{{ $repo->module_label ?? '—' }}</td>
                    <td class="small"><code>{{ $repo->api_reference ?? '—' }}</code></td>
                    <td><x-option-badge :option="$repo->status" /></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty message="No repositories match your filters." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $repositories->links() }}</div>
</x-layouts.app>
