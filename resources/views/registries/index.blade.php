@php
    use App\Support\Registries;
    $opts = app(\App\Support\Options\Options::class);
    $statusField = Registries::field($definition, 'status_id');
@endphp
<x-layouts.app :title="$definition['title']" :breadcrumbs="[$definition['title'] => null]">
    <x-page-header :title="$definition['title']" :subtitle="$definition['description']">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('registries.create', [$type] + array_filter(['product_id' => request('product_id'), 'repository_id' => request('repository_id')])) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New {{ strtolower($definition['singular']) }}</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <form class="card card-body mb-3 py-2" method="GET">
        <div class="row g-2 align-items-end">
            <div class="col-md-4"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Search…"></div>
            @foreach ($definition['filters'] as $filter)
                <div class="col-md-3">
                    <select name="{{ $filter }}" class="form-select form-select-sm" data-autosubmit>
                        @if ($filter === 'status_id')
                            <option value="">All statuses</option>
                            @foreach ($opts->for($statusField['group']) as $o)<option value="{{ $o->id }}" @selected(request($filter) == $o->id)>{{ $o->label }}</option>@endforeach
                        @elseif ($filter === 'repository_id')
                            <option value="">All repositories</option>
                            @foreach ($repositories as $id => $label)<option value="{{ $id }}" @selected(request($filter) == $id)>{{ $label }}</option>@endforeach
                        @else
                            <option value="">All products</option>
                            @foreach ($products as $id => $label)<option value="{{ $id }}" @selected(request($filter) == $id)>{{ $label }}</option>@endforeach
                        @endif
                    </select>
                </div>
            @endforeach
            <div class="col-md-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
        </div>
    </form>

    <div class="card"><div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
                <tr>@foreach ($definition['columns'] as $column)<th>{{ Registries::field($definition, $column)['label'] }}</th>@endforeach</tr>
            </thead>
            <tbody>
            @forelse ($records as $record)
                <tr>
                    @foreach ($definition['columns'] as $column)
                        <td>
                            @if ($column === 'code')
                                <a class="fw-semibold text-nowrap" href="{{ route('registries.show', [$type, $record->id]) }}">{{ $record->code }}</a>
                            @else
                                @include('registries._value', ['field' => Registries::field($definition, $column), 'record' => $record, 'compact' => true])
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($definition['columns']) }}"><x-empty message="No records match your filters." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $records->links() }}</div>
</x-layouts.app>
