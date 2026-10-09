<x-layouts.app title="Reports" :breadcrumbs="['Reports' => null]">
    <x-page-header title="Reports — portfolio overview" subtitle="Task progress for every product you can access. Click any number to open the matching tasks." />

    @include('reports.partials.nav')
    @include('reports.partials.filters', ['withArchived' => true])
    @include('reports.partials.totals', ['scope' => []])

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
            <span>Products</span>
            <span class="small text-muted">Columns follow the task statuses configured in Global Settings.</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Product</th><th>Status</th><th class="text-end">Modules</th>@include('reports.partials.head')</tr>
                </thead>
                <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td class="text-nowrap">
                            <a class="fw-semibold" href="{{ route('reports.product', [$product, ...$taskFilters]) }}">{{ $product->code }}</a>
                            <span class="text-muted">{{ $product->name }}</span>
                            @if ($product->isArchived())<span class="badge text-bg-dark">Archived</span>@endif
                        </td>
                        <td><x-option-badge :option="$product->status" /></td>
                        <td class="text-end"><a href="{{ route('modules.index', ['product' => $product->id]) }}">{{ $moduleCounts[$product->id] ?? 0 }}</a></td>
                        @include('reports.partials.row', ['summary' => $summaries[$product->id] ?? null, 'link' => ['product_id' => $product->id]])
                    </tr>
                @empty
                    <tr><td colspan="{{ $columns->count() + 8 }}"><x-empty message="You do not have access to any products yet." icon="bi-box-seam" /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $products->links() }}</div>
</x-layouts.app>
