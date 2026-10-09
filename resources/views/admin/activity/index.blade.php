<x-layouts.app title="Audit log" :breadcrumbs="['Administration' => null, 'Audit log' => null]">
    <x-page-header title="Audit log" subtitle="Who changed what and when. Passwords and sensitive notes are never recorded." />

    <form class="card card-body mb-3 py-2" method="GET">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="user_id" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All users</option>
                    @foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('user_id') == $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="subject_type" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All record types</option>
                    @foreach ($types as $type)<option value="{{ $type }}" @selected(request('subject_type') === $type)>{{ $type }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><input name="action" value="{{ request('action') }}" class="form-control form-control-sm" placeholder="Action prefix, e.g. access."></div>
            <div class="col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" aria-label="From"></div>
            <div class="col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" aria-label="To"></div>
            <div class="col-md-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
        </div>
    </form>

    <div class="card"><div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light"><tr><th>When</th><th>User</th><th>Action</th><th>Record</th><th>Changes</th><th>IP</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="small text-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td class="small">{{ $log->user?->name ?? 'System' }}</td>
                    <td><code class="small">{{ $log->action }}</code></td>
                    @if ($visibleIds->has($log->id))
                        <td class="small">
                            @php $subject = $log->subjectModel(); @endphp
                            @if ($subject && ($url = \App\Support\Links::url($subject)))<a href="{{ $url }}">{{ $log->subject_label }}</a>@else{{ $log->subject_label }}@endif
                            <div class="text-muted">{{ $log->subject_type }}</div>
                        </td>
                        <td class="small changes-cell">
                            @foreach ($log->changes ?? [] as $field => $change)
                                <div><code>{{ $field }}</code>:
                                    @if (is_array($change) && array_key_exists('from', $change))
                                        {{ is_scalar($change['from']) ? $change['from'] : json_encode($change['from']) }} → {{ is_scalar($change['to'] ?? null) ? $change['to'] : json_encode($change['to'] ?? null) }}
                                    @else
                                        {{ is_scalar($change) ? $change : json_encode($change) }}
                                    @endif
                                </div>
                            @endforeach
                        </td>
                    @else
                        <td class="small text-muted fst-italic" colspan="2"><i class="bi bi-lock"></i> Restricted record</td>
                    @endif
                    <td class="small text-muted">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty message="No activity recorded." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $logs->links() }}</div>
</x-layouts.app>
