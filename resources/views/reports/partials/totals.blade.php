@php
    $url = fn (array $extra) => route('tasks.index', [...$taskFilters, ...$scope, ...$extra]);
@endphp
<div class="row g-3 mb-3">
    @foreach ([
        ['Total tasks', $total->total, 'bi-list-check', $url(['state' => 'all']), ''],
        ['Active (open + blocked)', $total->active(), 'bi-hourglass-split', $url(['state' => 'open']), 'text-primary'],
        ['Blocked', $total->blocked, 'bi-slash-circle', $url(['state' => 'blocked']), 'text-danger'],
        ['Overdue', $total->overdue, 'bi-alarm', $url(['due' => 'overdue']), 'text-danger'],
        ['Due in '.\App\Support\Reports\TaskReport::DUE_SOON_DAYS.' days', $total->dueSoon, 'bi-calendar-event', $url(['due' => 'soon']), 'text-warning-emphasis'],
        ['Unassigned', $total->unassigned, 'bi-person-dash', $url(['unassigned' => 1]), ''],
        ['Closed', $total->closed, 'bi-check2-circle', $url(['state' => 'closed']), 'text-success'],
    ] as [$label, $value, $icon, $href, $class])
        <div class="col-6 col-md-3 col-xl">
            <a href="{{ $href }}" class="card stat-card text-decoration-none h-100"><div class="card-body py-3">
                <div class="small text-muted text-nowrap"><i class="bi {{ $icon }}"></i> {{ $label }}</div>
                <div class="stat-value {{ $class }}">{{ $value }}</div>
            </div></a>
        </div>
    @endforeach
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card stat-card h-100"><div class="card-body py-3">
            <div class="small text-muted"><i class="bi bi-graph-up"></i> Overall progress</div>
            <div class="stat-value">{{ $total->progress() }}%</div>
            <progress class="hub-progress w-100" max="100" value="{{ $total->progress() }}" aria-label="{{ $total->progress() }}% closed"></progress>
        </div></div>
    </div>
</div>
