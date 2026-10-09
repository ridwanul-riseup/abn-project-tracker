<x-layouts.app title="Workload report" :breadcrumbs="['Reports' => route('reports.index'), 'Workload' => null]">
    <x-page-header title="Reports — workload" subtitle="Tasks per assignee, limited to tasks you can access." />

    @include('reports.partials.nav')
    @include('reports.partials.filters', ['withProduct' => true])
    @include('reports.partials.totals', ['scope' => array_filter(['product_id' => request()->integer('product_id')])])

    @php $productScope = array_filter(['product_id' => request()->integer('product_id')]); @endphp
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Assignee</th><th>Role</th><th class="text-end">Active</th>@include('reports.partials.head')</tr>
                </thead>
                <tbody>
                @if ($unassigned->total > 0 && empty($taskFilters['assignee_id']))
                    <tr class="table-warning">
                        <td class="fst-italic">Unassigned</td><td></td>
                        <td class="text-end">{{ $unassigned->active() }}</td>
                        @include('reports.partials.row', ['summary' => $unassigned, 'link' => [...$productScope, 'unassigned' => 1]])
                    </tr>
                @endif
                @forelse ($people as $person)
                    @php $summary = $summaries[$person->id] ?? new \App\Support\Reports\TaskSummary; @endphp
                    <tr>
                        <td class="text-nowrap">{{ $person->name }} @unless ($person->is_active)<span class="badge text-bg-secondary">Inactive</span>@endunless</td>
                        <td class="small">{{ $person->role?->name ?? '—' }}</td>
                        <td class="text-end fw-semibold">{{ $summary->active() }}</td>
                        @include('reports.partials.row', ['summary' => $summary, 'link' => [...$productScope, 'assignee_id' => $person->id]])
                    </tr>
                @empty
                    @if ($unassigned->total === 0)
                        <tr><td colspan="{{ $columns->count() + 8 }}"><x-empty message="No tasks match your filters." icon="bi-people" /></td></tr>
                    @endif
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $people->links() }}</div>
</x-layouts.app>
