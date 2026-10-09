{{-- Task counts per option of one group (status, type or priority) for a summary set. --}}
@php
    $url = fn (array $extra) => route('tasks.index', [...$taskFilters, ...$scope, 'state' => 'all', ...$extra]);
    $grandTotal = max(1, $total->total);
@endphp
<div class="card h-100">
    <div class="card-header">{{ $title }}</div>
    <ul class="list-group list-group-flush small">
        @forelse ($rows as [$option, $count])
            <li class="list-group-item d-flex align-items-center gap-2">
                <span class="flex-grow-1"><x-option-badge :option="$option" /></span>
                <progress class="hub-progress" max="100" value="{{ (int) floor($count * 100 / $grandTotal) }}" aria-hidden="true"></progress>
                <span class="text-end distribution-count">@include('reports.partials.count', ['n' => $count, 'url' => $url([$param => $option->id])])</span>
            </li>
        @empty
            <li class="list-group-item text-muted">No tasks.</li>
        @endforelse
    </ul>
</div>
