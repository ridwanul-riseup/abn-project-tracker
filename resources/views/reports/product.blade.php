@php
    $opts = app(\App\Support\Options\Options::class);
    $scope = ['product_id' => $product->id];
    $optionRows = function (string $group, array $summaries) use ($opts) {
        return $opts->forFilter($group)
            ->map(fn ($o) => [$o, ($summaries[$o->id] ?? null)?->total ?? 0])
            ->filter(fn ($row) => $row[0]->is_active || $row[1] > 0)
            ->values();
    };
    $statusRows = $columns->map(fn ($o) => [$o, $total->count($o->id)]);
@endphp
<x-layouts.app :title="$product->code.' report'" :breadcrumbs="['Reports' => route('reports.index'), $product->code => null]">
    <x-page-header :title="'Report — '.$product->code.' · '.$product->name" subtitle="Task progress for this product, down to module level.">
        <x-slot:actions>
            <a href="{{ route('products.show', $product) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-seam"></i> Product page</a>
            <a href="{{ route('reports.workload', ['product_id' => $product->id, ...$taskFilters]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-people"></i> Workload</a>
        </x-slot:actions>
    </x-page-header>

    @include('reports.partials.nav')
    @include('reports.partials.filters')
    @include('reports.partials.totals')

    <div class="row g-3 mb-3">
        <div class="col-lg-4">@include('reports.partials.distribution', ['title' => 'By status', 'rows' => $statusRows, 'param' => 'status_id'])</div>
        <div class="col-lg-4">@include('reports.partials.distribution', ['title' => 'By type', 'rows' => $optionRows('task_type', $byType), 'param' => 'type_id'])</div>
        <div class="col-lg-4">@include('reports.partials.distribution', ['title' => 'By priority', 'rows' => $optionRows('priority', $byPriority), 'param' => 'priority_id'])</div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Modules</div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Module</th><th>Application</th><th>Module status</th><th>Priority</th>@include('reports.partials.head')</tr>
                </thead>
                <tbody>
                @forelse ($modules as $module)
                    <tr>
                        <td>
                            <a class="fw-semibold" href="{{ route('modules.show', $module) }}">{{ $module->code }}</a>
                            <div class="small text-muted">{{ $module->name }}</div>
                            @if ($module->isArchived())<span class="badge text-bg-dark">Archived</span>@endif
                        </td>
                        <td class="small">{{ $module->application?->name ?? '—' }}</td>
                        <td><x-option-badge :option="$module->status" /></td>
                        <td><x-option-badge :option="$module->priority" /></td>
                        @include('reports.partials.row', ['summary' => $summaries[$module->id] ?? null, 'link' => ['module_id' => $module->id]])
                    </tr>
                @empty
                    <tr><td colspan="{{ $columns->count() + 9 }}"><x-empty message="No modules recorded for this product." icon="bi-puzzle" /></td></tr>
                @endforelse
                </tbody>
                @if ($unlinked->total > 0 || $outside->total > 0)
                    <tfoot class="table-group-divider">
                        @if ($unlinked->total > 0)
                            <tr>
                                <td colspan="4" class="fst-italic">Not linked to a module</td>
                                @include('reports.partials.row', ['summary' => $unlinked, 'link' => [...$scope, 'no_module' => 1]])
                            </tr>
                        @endif
                        @if ($outside->total > 0)
                            <tr>
                                <td colspan="4" class="fst-italic text-muted">In modules outside your access</td>
                                @include('reports.partials.row', ['summary' => $outside, 'link' => null])
                            </tr>
                        @endif
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
    <div class="mb-3">{{ $modules->links() }}</div>

    <div class="row g-3">
        @foreach ([
            ['Overdue', $overdueTasks, 'bi-alarm text-danger', ['due' => 'overdue'], 'Nothing is overdue.'],
            ['Due in the next '.\App\Support\Reports\TaskReport::DUE_SOON_DAYS.' days', $dueSoonTasks, 'bi-calendar-event text-warning', ['due' => 'soon'], 'Nothing is due soon.'],
            ['Blocked', $blockedTasks, 'bi-slash-circle text-danger', ['state' => 'blocked'], 'Nothing is blocked.'],
        ] as [$heading, $list, $icon, $params, $emptyText])
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between">
                        <span><i class="bi {{ $icon }}"></i> {{ $heading }}</span>
                        <a class="small" href="{{ route('tasks.index', [...$taskFilters, ...$scope, ...$params]) }}">View all</a>
                    </div>
                    <ul class="list-group list-group-flush small">
                        @forelse ($list as $task)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between gap-2">
                                    <span><a href="{{ route('tasks.show', $task) }}" class="fw-semibold">{{ $task->key }}</a> {{ \Illuminate\Support\Str::limit($task->title, 45) }}</span>
                                    <x-option-badge :option="$task->status" />
                                </div>
                                <div class="text-muted">
                                    {{ $task->assignee?->name ?? 'Unassigned' }}
                                    @if ($task->module) · <x-ref :model="$task->module" />@endif
                                    @if ($task->due_date) · due {{ $task->due_date->format('Y-m-d') }}@endif
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-muted">{{ $emptyText }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.app>
