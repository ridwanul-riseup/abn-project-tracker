<x-layouts.app title="Needs Clarification" :breadcrumbs="['Needs Clarification' => null]">
    <x-page-header title="Needs Clarification" subtitle="Missing or ambiguous information found in the source workbook or raised later. Values were not invented; resolve each item once the correct information is confirmed." />

    <ul class="nav nav-pills mb-2">
        @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'all' => 'All'] as $key => $label)
            <li class="nav-item"><a class="nav-link py-1 @if ($status === $key) active @endif" href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}">{{ $label }}</a></li>
        @endforeach
    </ul>
    <form class="card card-body mb-3 py-2" method="GET">
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="row g-2">
            <div class="col-md-4"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Search…"></div>
            <div class="col-md-3">
                <select name="product_id" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All products / global</option>
                    @foreach ($products as $id => $label)<option value="{{ $id }}" @selected(request('product_id') == $id)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
        </div>
    </form>

    <div class="card"><div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light"><tr><th>Record</th><th>Field</th><th>Issue</th><th>Original value</th><th>Source</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($items as $item)
                <tr>
                    <td class="small text-nowrap">@if ($item->subject)<x-ref :model="$item->subject" />@elseif ($item->product)<x-ref :model="$item->product" />@else<span class="text-muted">Global</span>@endif</td>
                    <td class="small"><code>{{ $item->field ?? '—' }}</code></td>
                    <td class="small">{{ $item->issue }}</td>
                    <td class="small text-muted">{{ $item->original_value ?? '—' }}</td>
                    <td class="small text-muted">{{ $item->source }}</td>
                    <td class="small">
                        @if ($item->status === 'resolved')
                            <span class="badge bg-success">Resolved</span>
                            <div class="text-muted">{{ $item->resolution }}<br>{{ $item->resolver?->name }} · {{ $item->resolved_at?->format('Y-m-d') }}</div>
                        @else
                            <span class="badge bg-warning text-dark">Open</span>
                        @endif
                    </td>
                    <td class="small">
                        @can('update', $item)
                            @if ($item->status === 'open')
                                <form method="POST" action="{{ route('clarifications.resolve', $item) }}" class="d-flex gap-1">
                                    @csrf
                                    <input name="resolution" class="form-control form-control-sm" placeholder="Resolution…" required maxlength="2000">
                                    <button class="btn btn-sm btn-outline-success text-nowrap">Resolve</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('clarifications.reopen', $item) }}">@csrf<button class="btn btn-sm btn-link">Reopen</button></form>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty message="Nothing needs clarification." icon="bi-check-circle" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $items->links() }}</div>
</x-layouts.app>
