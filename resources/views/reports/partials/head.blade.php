<th class="text-end">Total</th>
@foreach ($columns as $status)
    <th class="text-end text-nowrap"><x-option-badge :option="$status" /></th>
@endforeach
<th class="text-end text-nowrap" title="Past due date and not closed">Overdue</th>
<th class="text-end text-nowrap" title="Due within {{ \App\Support\Reports\TaskReport::DUE_SOON_DAYS }} days and not closed">Due soon</th>
<th class="text-end text-nowrap" title="Not closed and nobody assigned">Unassigned</th>
<th class="text-nowrap" title="Closed tasks (done or cancelled) as a share of all tasks">Progress</th>
