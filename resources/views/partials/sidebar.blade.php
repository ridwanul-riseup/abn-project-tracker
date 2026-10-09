@php
    $nav = [
        'Overview' => [
            ['Dashboard', 'dashboard', 'bi-speedometer2', 'dashboard', true],
        ],
        'Inventory' => [
            ['Products', 'products.index', 'bi-box-seam', 'products.*', true],
            ['Applications', 'applications.index', 'bi-window-stack', 'applications.*', true],
            ['Repositories', 'repositories.index', 'bi-git', 'repositories.*', true],
            ['Modules', 'modules.index', 'bi-puzzle', 'modules.*', true],
            ['Dependencies', 'dependencies.index', 'bi-diagram-3', 'dependencies.*', true],
            ['Needs Clarification', 'clarifications.index', 'bi-question-circle', 'clarifications.*', true],
        ],
        'Work' => [
            ['Documentation', 'docs.index', 'bi-journal-text', 'docs.*', true],
            ['Tasks', 'tasks.index', 'bi-check2-square', 'tasks.*', true],
        ],
        'References' => [
            ['APIs', 'registries.index:apis', 'bi-plug', 'apis', true],
            ['Integrations', 'registries.index:integrations', 'bi-arrow-left-right', 'integrations', true],
            ['Databases', 'registries.index:databases', 'bi-database', 'databases', true],
            ['Figma', 'registries.index:figma', 'bi-palette', 'figma', true],
            ['Environments', 'registries.index:environments', 'bi-hdd-network', 'environments', true],
            ['Deployments', 'registries.index:deployments', 'bi-cloud-upload', 'deployments', true],
            ['Releases', 'registries.index:releases', 'bi-tag', 'releases', true],
        ],
        'Administration' => [
            ['Users & Access', 'admin.users.index', 'bi-people', 'admin.users.*', Gate::check('users.manage') || Gate::check('access.manage')],
            ['Roles & Permissions', 'admin.roles.index', 'bi-shield-lock', 'admin.roles.*', Gate::check('roles.manage')],
            ['Global Settings', 'admin.options.index', 'bi-sliders', 'admin.options.*', Gate::check('settings.manage')],
            ['Activity History', 'admin.activity.index', 'bi-clock-history', 'admin.activity.*', Gate::check('audit.view')],
        ],
    ];
    $registryType = request()->route('type');
@endphp
<aside class="hub-sidebar flex-shrink-0 p-2">
    <div class="px-3 py-3 d-flex align-items-center justify-content-between">
        <a href="{{ route('dashboard') }}" class="hub-brand"><i class="bi bi-hexagon-half"></i> AB Project Hub</a>
        <button class="btn btn-sm btn-outline-light d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#hubNav" aria-label="Toggle navigation">
            <i class="bi bi-list"></i>
        </button>
    </div>
    <div class="collapse d-lg-block" id="hubNav">
        @foreach ($nav as $heading => $items)
            @php $visible = array_filter($items, fn ($i) => $i[4]); @endphp
            @if ($visible)
                <div class="nav-heading">{{ $heading }}</div>
                <ul class="nav flex-column">
                    @foreach ($visible as [$label, $route, $icon, $pattern])
                        @php
                            if (str_starts_with($route, 'registries.index:')) {
                                $type = substr($route, strlen('registries.index:'));
                                $url = route('registries.index', $type);
                                $active = request()->routeIs('registries.*') && $registryType === $type;
                            } else {
                                $url = route($route);
                                $active = request()->routeIs($pattern);
                            }
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link {{ $active ? 'active' : '' }}" href="{{ $url }}"><i class="bi {{ $icon }} me-2"></i>{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endforeach
    </div>
</aside>
