@php
    $opts = app(\App\Support\Options\Options::class);
    $types = \App\Models\ComponentDependency::COMPONENT_TYPES;
@endphp
<x-layouts.app title="Dependencies" :breadcrumbs="['Dependencies' => null]">
    <x-page-header title="Dependencies" subtitle="“Source depends on target.” Only dependencies where you can see both ends are listed.">
        <x-slot:actions>
            @can('inventory.write')
                <a href="{{ route('dependencies.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New dependency</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form class="card card-body mb-3 py-2" method="GET">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Search ID, description, impact"></div>
            <div class="col-md-2">
                <select name="product" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All products</option>
                    @foreach ($products as $id => $label)<option value="{{ $id }}" @selected(request('product') == $id)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All types</option>
                    @foreach ($opts->forFilter('dependency_type') as $o)<option value="{{ $o->id }}" @selected(request('type') == $o->id)>{{ $o->filterLabel() }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="criticality" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All criticality</option>
                    @foreach ($opts->forFilter('criticality') as $o)<option value="{{ $o->id }}" @selected(request('criticality') == $o->id)>{{ $o->filterLabel() }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All statuses</option>
                    @foreach ($opts->forFilter('dependency_status') as $o)<option value="{{ $o->id }}" @selected(request('status') == $o->id)>{{ $o->filterLabel() }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
        </div>
        @if (request('component'))
            <div class="small mt-2">Filtered by component <code>{{ request('component') }}</code> · <a href="{{ route('dependencies.index') }}">clear</a></div>
        @endif
    </form>

    <div class="card"><div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light"><tr><th>ID</th><th>Source</th><th></th><th>Target</th><th>Type</th><th>Criticality</th><th>Failure impact</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($dependencies as $dep)
                <tr>
                    <td><a class="fw-semibold" href="{{ route('dependencies.edit', $dep) }}">{{ $dep->code }}</a></td>
                    <td><span class="text-muted small">{{ $types[$dep->source_type] ?? '' }}</span><br><x-ref :model="$dep->source" :name="true" /></td>
                    <td><i class="bi bi-arrow-right"></i></td>
                    <td><span class="text-muted small">{{ $types[$dep->target_type] ?? '' }}</span><br><x-ref :model="$dep->target" :name="true" /></td>
                    <td><x-option-badge :option="$dep->dependencyType" /></td>
                    <td><x-option-badge :option="$dep->criticality" /></td>
                    <td class="small">{{ $dep->failure_impact ?? '—' }}</td>
                    <td><x-option-badge :option="$dep->status" /></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty message="No dependencies match your filters." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $dependencies->links() }}</div>
</x-layouts.app>
