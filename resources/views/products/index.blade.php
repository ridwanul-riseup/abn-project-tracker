<x-layouts.app title="Products" :breadcrumbs="['Products' => null]">
    <x-page-header title="Products" subtitle="Products of the AB Digital Ecosystem you are authorized to see.">
        <x-slot:actions>
            @can('products.create')
                <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New product</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form class="card card-body mb-3 py-2" method="GET">
        <div class="row g-2 align-items-end">
            <div class="col-md-5"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Search code, name, description"></div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All statuses</option>
                    @foreach (app(\App\Support\Options\Options::class)->forFilter('product_status') as $o)
                        <option value="{{ $o->id }}" @selected(request('status') == $o->id)>{{ $o->filterLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 form-check ms-2">
                <input class="form-check-input" type="checkbox" name="archived" value="1" id="archived" @checked(request('archived'))>
                <label class="form-check-label small" for="archived">Include archived</label>
            </div>
            <div class="col-md-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>ID</th><th>Product</th><th>Primary owner</th><th>Status</th><th class="text-end">Apps</th><th class="text-end">Repos</th><th class="text-end">Modules</th></tr></thead>
                <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td><a href="{{ route('products.show', $product) }}" class="fw-semibold">{{ $product->code }}</a></td>
                        <td>
                            {{ $product->name }}
                            @if ($product->isArchived())<span class="badge text-bg-dark ms-1">Archived</span>@endif
                            <div class="small text-muted">{{ \Illuminate\Support\Str::limit($product->description, 110) }}</div>
                        </td>
                        <td>{{ $product->primary_owner ?? '—' }}</td>
                        <td><x-option-badge :option="$product->status" /></td>
                        <td class="text-end">{{ $product->applications_count }}</td>
                        <td class="text-end">{{ $product->repositories_count }}</td>
                        <td class="text-end">{{ $product->modules_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty message="No products match your filters, or you have not been granted access to any product." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $products->links() }}</div>
</x-layouts.app>
