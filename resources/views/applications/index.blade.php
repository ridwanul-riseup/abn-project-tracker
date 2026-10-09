@php $opts = app(\App\Support\Options\Options::class); @endphp
<x-layouts.app title="Applications" :breadcrumbs="['Applications' => null]">
    <x-page-header title="Applications" subtitle="Applications belong to a product and group its repositories.">
        <x-slot:actions>
            @can('inventory.write')
                <a href="{{ route('applications.create', ['product_id' => request('product')]) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New application</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form class="card card-body mb-3 py-2" method="GET">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Search ID or name"></div>
            <div class="col-md-3">
                <select name="product" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All products</option>
                    @foreach ($products as $id => $label)<option value="{{ $id }}" @selected(request('product') == $id)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All types</option>
                    @foreach ($opts->for('application_type') as $o)<option value="{{ $o->id }}" @selected(request('type') == $o->id)>{{ $o->label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="classification" class="form-select form-select-sm" data-autosubmit>
                    <option value="">Frontend/backend</option>
                    @foreach ($opts->for('application_classification') as $o)<option value="{{ $o->id }}" @selected(request('classification') == $o->id)>{{ $o->label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
        </div>
    </form>

    <div class="card"><div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>ID</th><th>Application</th><th>Product</th><th>Type</th><th>FE/BE</th><th>Status</th><th class="text-end">Repos</th></tr></thead>
            <tbody>
            @forelse ($applications as $app)
                <tr>
                    <td><a class="fw-semibold" href="{{ route('applications.show', $app) }}">{{ $app->code }}</a></td>
                    <td>{{ $app->name }} @if ($app->isArchived())<span class="badge text-bg-dark">Archived</span>@endif</td>
                    <td><x-ref :model="$app->product" /></td>
                    <td>{{ $app->applicationType?->label ?? '—' }}</td>
                    <td>{{ $app->classification?->label ?? '—' }}</td>
                    <td><x-option-badge :option="$app->status" /></td>
                    <td class="text-end">{{ $app->repositories_count }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty message="No applications match your filters." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $applications->links() }}</div>
</x-layouts.app>
