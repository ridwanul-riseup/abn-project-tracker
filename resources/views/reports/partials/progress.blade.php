@if ($summary->total > 0)
    <progress class="hub-progress" max="100" value="{{ $summary->progress() }}" aria-label="{{ $summary->progress() }}% closed"></progress>
    <span class="small text-muted ms-1">{{ $summary->progress() }}%</span>
@else
    <span class="small text-body-tertiary">No tasks</span>
@endif
