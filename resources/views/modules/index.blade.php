@php $opts = app(\App\Support\Options\Options::class); @endphp
<x-layouts.app title="Modules" :breadcrumbs="['Modules' => null]">
    <x-page-header title="Module & feature registry" subtitle="Functional modules of each product, their repositories and references.">
        <x-slot:actions>
            @can('inventory.write')
                <a href="{{ route('modules.create', ['product_id' => request('product')]) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New module</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form class="card card-body mb-3 py-2" method="GET">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Search ID, name, description"></div>
            <div class="col-md-3">
                <select name="product" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All products</option>
                    @foreach ($products as $id => $label)<option value="{{ $id }}" @selected(request('product') == $id)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All statuses</option>
                    @foreach ($opts->for('module_status') as $o)<option value="{{ $o->id }}" @selected(request('status') == $o->id)>{{ $o->label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="priority" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All priorities</option>
                    @foreach ($opts->for('priority') as $o)<option value="{{ $o->id }}" @selected(request('priority') == $o->id)>{{ $o->label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
        </div>
    </form>

    <div class="card"><div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light"><tr><th>ID</th><th>Module</th><th>Product</th><th>Application</th><th>Primary repository</th><th>Owner</th><th>Status</th><th>Priority</th></tr></thead>
            <tbody>
            @forelse ($modules as $module)
                <tr>
                    <td><a class="fw-semibold" href="{{ route('modules.show', $module) }}">{{ $module->code }}</a></td>
                    <td>{{ $module->name }} @if ($module->isArchived())<span class="badge text-bg-dark">Archived</span>@endif</td>
                    <td><x-ref :model="$module->product" /></td>
                    <td>{{ $module->application?->name ?? '—' }}</td>
                    <td><x-ref :model="$module->primaryRepository" /></td>
                    <td>{{ $module->owner ?? '—' }}</td>
                    <td><x-option-badge :option="$module->status" /></td>
                    <td><x-option-badge :option="$module->priority" /></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty message="No modules match your filters." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $modules->links() }}</div>
</x-layouts.app>
