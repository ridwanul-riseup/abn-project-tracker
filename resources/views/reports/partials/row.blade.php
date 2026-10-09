@php
    /** @var \App\Support\Reports\TaskSummary $s */
    $s = $summary ?? new \App\Support\Reports\TaskSummary;
    $url = fn (array $extra) => $link === null ? null : route('tasks.index', [...$taskFilters, ...$link, ...$extra]);
@endphp
<td class="text-end fw-semibold">@include('reports.partials.count', ['n' => $s->total, 'url' => $url(['state' => 'all'])])</td>
@foreach ($columns as $status)
    <td class="text-end">@include('reports.partials.count', ['n' => $s->count($status->id), 'url' => $url(['state' => 'all', 'status_id' => $status->id])])</td>
@endforeach
<td class="text-end">@include('reports.partials.count', ['n' => $s->overdue, 'url' => $url(['due' => 'overdue']), 'class' => 'text-danger fw-semibold'])</td>
<td class="text-end">@include('reports.partials.count', ['n' => $s->dueSoon, 'url' => $url(['due' => 'soon']), 'class' => 'text-warning-emphasis'])</td>
<td class="text-end">@include('reports.partials.count', ['n' => $s->unassigned, 'url' => $url(['unassigned' => 1])])</td>
<td class="text-nowrap">@include('reports.partials.progress', ['summary' => $s])</td>
