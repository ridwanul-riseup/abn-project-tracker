<x-layouts.app title="Search" :breadcrumbs="['Search' => null]">
    <x-page-header title="Search" subtitle="Results include only records you have access to." />
    <form method="GET" action="{{ route('search') }}" class="mb-3">
        <div class="input-group">
            <input type="search" name="q" class="form-control" value="{{ $q }}" placeholder="Search products, repositories, modules, documents, tasks, APIs…" autofocus minlength="2" maxlength="100">
            <button class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
        </div>
    </form>

    @if (mb_strlen($q) < 2)
        <p class="text-muted">Enter at least two characters.</p>
    @elseif (empty($groups))
        <x-empty message="No results for “{{ $q }}”." icon="bi-search" />
    @else
        @foreach ($groups as $label => $results)
            <div class="card mb-3">
                <div class="card-header">{{ $label }} <span class="badge bg-light text-dark">{{ $results->count() }}</span></div>
                <ul class="list-group list-group-flush">
                    @foreach ($results as $result)
                        <li class="list-group-item">
                            <x-ref :model="$result['model']" :name="true" class="fw-semibold" />
                            @if ($result['model'] instanceof \App\Models\Document)<code class="small ms-1 text-muted">{{ $result['model']->path }}</code>@endif
                            @if ($result['snippet'])<div class="small text-muted">{{ $result['snippet'] }}</div>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    @endif
</x-layouts.app>
