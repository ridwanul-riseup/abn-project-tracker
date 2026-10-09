<x-layouts.app title="Module report" :breadcrumbs="['Reports' => route('reports.index'), 'Modules' => null]">
    <x-page-header title="Reports — modules" subtitle="Task progress per module across all products you can access. Tasks not linked to a module appear in each product's report." />

    @include('reports.partials.nav')
    @include('reports.partials.filters', ['withProduct' => true, 'withModuleStatus' => true, 'withArchived' => true])
    @include('reports.partials.totals', ['scope' => array_filter(['product_id' => request()->integer('product_id')])])

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Module</th><th>Product</th><th>Module status</th><th>Priority</th>@include('reports.partials.head')</tr>
                </thead>
                <tbody>
                @forelse ($modules as $module)
                    <tr>
                        <td>
                            <a class="fw-semibold" href="{{ route('modules.show', $module) }}">{{ $module->code }}</a>
                            <div class="small text-muted">{{ $module->name }}</div>
                            @if ($module->isArchived())<span class="badge text-bg-dark">Archived</span>@endif
                        </td>
                        <td class="text-nowrap">
                            @if (app(\App\Support\Access\AccessService::class)->canRead(auth()->user(), $module->product))
                                <a href="{{ route('reports.product', [$module->product, ...$taskFilters]) }}">{{ $module->product->code }}</a>
                            @else
                                <x-ref :model="$module->product" />
                            @endif
                        </td>
                        <td><x-option-badge :option="$module->status" /></td>
                        <td><x-option-badge :option="$module->priority" /></td>
                        @include('reports.partials.row', ['summary' => $summaries[$module->id] ?? null, 'link' => ['module_id' => $module->id]])
                    </tr>
                @empty
                    <tr><td colspan="{{ $columns->count() + 9 }}"><x-empty message="No modules match your filters." icon="bi-puzzle" /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $modules->links() }}</div>
</x-layouts.app>
