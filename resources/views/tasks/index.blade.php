@php $opts = app(\App\Support\Options\Options::class); @endphp
<x-layouts.app title="Tasks" :breadcrumbs="['Tasks' => null]">
    <x-page-header title="Tasks" subtitle="Development tasks across products. Only tasks within your access are shown.">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('tasks.create', array_filter(['product_id' => request('product_id')])) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New task</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <ul class="nav nav-pills nav-sm mb-2">
        @foreach (['open' => 'Open', 'blocked' => 'Blocked', 'closed' => 'Closed', 'all' => 'All'] as $key => $label)
            <li class="nav-item"><a class="nav-link py-1 @if ($state === $key) active @endif" href="{{ request()->fullUrlWithQuery(['state' => $key, 'page' => null]) }}">{{ $label }}</a></li>
        @endforeach
        <li class="nav-item ms-2"><a class="nav-link py-1 @if (request()->boolean('mine')) active @endif" href="{{ request()->fullUrlWithQuery(['mine' => request()->boolean('mine') ? null : 1, 'page' => null]) }}"><i class="bi bi-person"></i> Assigned to me</a></li>
    </ul>

    @php
        $extraFilters = array_filter([
            'due' => ['overdue' => 'Overdue', 'soon' => 'Due in the next '.\App\Support\Reports\TaskReport::DUE_SOON_DAYS.' days'][request('due')] ?? null,
            'unassigned' => request()->boolean('unassigned') ? 'Unassigned' : null,
            'no_module' => request()->boolean('no_module') ? 'Not linked to a module' : null,
            'module_id' => request()->integer('module_id') ? 'Module: '.($filterModule?->code ?? 'selected') : null,
            'repository_id' => request()->integer('repository_id') ? 'Repository: '.($filterRepository?->code ?? 'selected') : null,
        ]);
    @endphp
    @if ($extraFilters)
        <div class="mb-2 d-flex flex-wrap gap-1 align-items-center small">
            <span class="text-muted">Also filtered by:</span>
            @foreach ($extraFilters as $param => $label)
                <a class="badge text-bg-light border text-decoration-none" href="{{ request()->fullUrlWithQuery([$param => null, 'page' => null]) }}" title="Remove this filter">{{ $label }} <i class="bi bi-x"></i></a>
            @endforeach
        </div>
    @endif

    <form class="card card-body mb-3 py-2" method="GET">
        <input type="hidden" name="state" value="{{ $state }}">
        @if (request()->boolean('mine'))<input type="hidden" name="mine" value="1">@endif
        @foreach (array_keys($extraFilters) as $param)<input type="hidden" name="{{ $param }}" value="{{ request($param) }}">@endforeach
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Key or title…"></div>
            <div class="col-md-2">
                <select name="product_id" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All products</option>
                    @foreach ($products as $id => $label)<option value="{{ $id }}" @selected(request('product_id') == $id)>{{ $label }}</option>@endforeach
                </select>
            </div>
            @foreach (['type_id' => ['task_type', 'All types'], 'status_id' => ['task_status', 'All statuses'], 'priority_id' => ['priority', 'All priorities']] as $name => [$group, $placeholder])
                <div class="col-md-2">
                    <select name="{{ $name }}" class="form-select form-select-sm" data-autosubmit>
                        <option value="">{{ $placeholder }}</option>
                        @foreach ($opts->forFilter($group) as $o)<option value="{{ $o->id }}" @selected(request($name) == $o->id)>{{ $o->filterLabel() }}</option>@endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-md-2">
                <select name="assignee_id" class="form-select form-select-sm" data-autosubmit>
                    <option value="">Any assignee</option>
                    @foreach ($assignees as $id => $name)<option value="{{ $id }}" @selected(request('assignee_id') == $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="sort" class="form-select form-select-sm" data-autosubmit>
                    <option value="updated" @selected(request('sort', 'updated') === 'updated')>Recently updated</option>
                    <option value="due" @selected(request('sort') === 'due')>Due date</option>
                    <option value="key" @selected(request('sort') === 'key')>Key</option>
                </select>
            </div>
            <div class="col-md-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
        </div>
    </form>

    <div class="card"><div class="table-responsive">
        <table class="table table-hover table-sm mb-0 align-middle">
            <thead class="table-light"><tr><th>Key</th><th>Title</th><th>Type</th><th>Status</th><th>Priority</th><th>Assignee</th><th>Scope</th><th>Due</th></tr></thead>
            <tbody>
            @forelse ($tasks as $task)
                <tr>
                    <td class="text-nowrap"><a class="fw-semibold" href="{{ route('tasks.show', $task) }}">{{ $task->key }}</a></td>
                    <td>{{ $task->title }}@if ($task->blocker_description && $task->status?->behavior === 'blocked')<div class="small text-danger"><i class="bi bi-slash-circle"></i> {{ \Illuminate\Support\Str::limit($task->blocker_description, 90) }}</div>@endif</td>
                    <td><x-option-badge :option="$task->type" /></td>
                    <td><x-option-badge :option="$task->status" /></td>
                    <td><x-option-badge :option="$task->priority" /></td>
                    <td class="small">{{ $task->assignee?->name ?? '—' }}</td>
                    <td class="small">@if ($task->module)<x-ref :model="$task->module" />@elseif ($task->repository)<x-ref :model="$task->repository" />@else<x-ref :model="$task->product" />@endif</td>
                    <td class="small text-nowrap @if ($task->isOverdue()) text-danger fw-semibold @endif">{{ $task->due_date?->format('Y-m-d') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty message="No tasks match your filters." icon="bi-check2-square" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $tasks->links() }}</div>
</x-layouts.app>
