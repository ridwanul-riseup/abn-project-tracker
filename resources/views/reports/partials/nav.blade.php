<ul class="nav nav-tabs mb-3">
    @foreach ([
        ['reports.index', 'Portfolio overview', 'bi-grid-3x3-gap', 'reports.index'],
        ['reports.modules', 'Modules', 'bi-puzzle', 'reports.modules'],
        ['reports.workload', 'Workload', 'bi-people', 'reports.workload'],
    ] as [$route, $label, $icon, $pattern])
        <li class="nav-item">
            <a class="nav-link @if (request()->routeIs($pattern)) active @endif" href="{{ route($route, $taskFilters) }}"><i class="bi {{ $icon }}"></i> {{ $label }}</a>
        </li>
    @endforeach
    @isset($product)
        <li class="nav-item"><a class="nav-link active" href="{{ request()->fullUrl() }}"><i class="bi bi-box-seam"></i> {{ $product->code }}</a></li>
    @endisset
</ul>
