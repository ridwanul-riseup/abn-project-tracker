<x-layouts.app title="Dashboard">
    <x-page-header title="Dashboard" subtitle="AB Digital Ecosystem — products, repositories, documentation and development tasks you have access to.">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-bar-chart-line"></i> Progress reports</a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-3">
        @foreach ([
            ['Repositories', $counts['repositories'], 'bi-git', route('repositories.index')],
            ['Modules', $counts['modules'], 'bi-puzzle', route('modules.index')],
            ['Documents', $counts['documents'], 'bi-journal-text', route('docs.index')],
            ['Open tasks', $counts['open_tasks'], 'bi-check2-square', route('tasks.index')],
            ['Blocked tasks', $counts['blocked_tasks'], 'bi-slash-circle', route('tasks.index', ['state' => 'blocked'])],
            ['Needs clarification', $counts['clarifications'], 'bi-question-circle', route('clarifications.index')],
        ] as [$label, $value, $icon, $url])
            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ $url }}" class="card text-decoration-none h-100"><div class="card-body py-3">
                    <div class="small text-muted"><i class="bi {{ $icon }}"></i> {{ $label }}</div>
                    <div class="fs-4 fw-semibold text-body">{{ $value }}</div>
                </div></a>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between"><span>Products</span><a href="{{ route('products.index') }}" class="small">All products</a></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light"><tr><th>Code</th><th>Product</th><th>Status</th><th class="text-end">Repositories</th><th class="text-end">Modules</th></tr></thead>
                        <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td><a class="fw-semibold" href="{{ route('products.show', $product) }}">{{ $product->code }}</a></td>
                                <td>{{ $product->name }}</td>
                                <td><x-option-badge :option="$product->status" /></td>
                                <td class="text-end"><a href="{{ route('repositories.index', ['product_id' => $product->id]) }}">{{ $repoCounts[$product->id] ?? 0 }}</a></td>
                                <td class="text-end"><a href="{{ route('modules.index', ['product_id' => $product->id]) }}">{{ $moduleCounts[$product->id] ?? 0 }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty message="You do not have access to any products yet. Ask an administrator to grant access." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between"><span>My open tasks</span><a href="{{ route('tasks.index', ['mine' => 1]) }}" class="small">All</a></div>
                        <ul class="list-group list-group-flush small">
                            @forelse ($myTasks as $task)
                                <li class="list-group-item d-flex justify-content-between gap-2">
                                    <span><a href="{{ route('tasks.show', $task) }}" class="fw-semibold">{{ $task->key }}</a> {{ \Illuminate\Support\Str::limit($task->title, 50) }}</span>
                                    <span class="text-nowrap"><x-option-badge :option="$task->status" />@if ($task->due_date) <span class="@if ($task->isOverdue()) text-danger @else text-muted @endif">{{ $task->due_date->format('M j') }}</span>@endif</span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted">No open tasks assigned to you.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">Blocked tasks</div>
                        <ul class="list-group list-group-flush small">
                            @forelse ($blockedTasks as $task)
                                <li class="list-group-item">
                                    <a href="{{ route('tasks.show', $task) }}" class="fw-semibold">{{ $task->key }}</a> {{ \Illuminate\Support\Str::limit($task->title, 50) }}
                                    <div class="text-danger">{{ \Illuminate\Support\Str::limit($task->blocker_description, 90) }}</div>
                                </li>
                            @empty
                                <li class="list-group-item text-muted">Nothing is blocked.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between"><span>Recently updated documents</span><a href="{{ route('docs.index') }}" class="small">All</a></div>
                <ul class="list-group list-group-flush small">
                    @forelse ($recentDocuments as $doc)
                        <li class="list-group-item">
                            <a href="{{ route('docs.show', $doc) }}">{{ $doc->title }}</a>
                            <div class="text-muted">{{ $doc->content_updated_at?->diffForHumans() }} · {{ $doc->editor?->name ?? 'import' }}</div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No documents.</li>
                    @endforelse
                </ul>
            </div>
            <div class="card">
                <div class="card-header d-flex justify-content-between"><span>Recent activity</span>@can('audit.view')<a href="{{ route('admin.activity.index') }}" class="small">Audit log</a>@endcan</div>
                <ul class="list-group list-group-flush small">
                    @forelse ($activity as $log)
                        <li class="list-group-item">@include('partials.activity-item', ['log' => $log])</li>
                    @empty
                        <li class="list-group-item text-muted">No recent activity.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-layouts.app>
