@php
    $registries = Gate::check('registries.view');
    $nav = [
        'Overview' => [
            ['Dashboard', 'dashboard', 'bi-speedometer2', 'dashboard', true],
            ['Reports', 'reports.index', 'bi-bar-chart-line', 'reports.*', Gate::check('reports.view')],
        ],
        'Inventory' => [
            ['Products', 'products.index', 'bi-box-seam', 'products.*', Gate::check('products.view')],
            ['Applications', 'applications.index', 'bi-window-stack', 'applications.*', Gate::check('applications.view')],
            ['App credentials', 'application-credentials.index', 'bi-key', 'application-credentials.*', Gate::check('applications.view')],
            ['Repositories', 'repositories.index', 'bi-git', 'repositories.*', Gate::check('repositories.view')],
            ['Modules', 'modules.index', 'bi-puzzle', 'modules.*', Gate::check('modules.view')],
            ['Dependencies', 'dependencies.index', 'bi-diagram-3', 'dependencies.*', Gate::check('dependencies.view')],
            ['Needs Clarification', 'clarifications.index', 'bi-question-circle', 'clarifications.*', Gate::check('clarifications.view')],
        ],
        'Work' => [
            ['Documentation', 'docs.index', 'bi-journal-text', 'docs.*', Gate::check('docs.view')],
            ['Tasks', 'tasks.index', 'bi-check2-square', 'tasks.*', Gate::check('tasks.view')],
        ],
        'References' => [
            ['APIs', 'registries.index:apis', 'bi-plug', 'apis', $registries],
            ['Integrations', 'registries.index:integrations', 'bi-arrow-left-right', 'integrations', $registries],
            ['Databases', 'registries.index:databases', 'bi-database', 'databases', $registries],
            ['Figma', 'registries.index:figma', 'bi-palette', 'figma', $registries],
            ['Environments', 'registries.index:environments', 'bi-hdd-network', 'environments', $registries],
            ['Deployments', 'registries.index:deployments', 'bi-cloud-upload', 'deployments', $registries],
            ['Releases', 'registries.index:releases', 'bi-tag', 'releases', $registries],
        ],
        'Administration' => [
            ['Users & Access', 'admin.users.index', 'bi-people', 'admin.users.*', Gate::check('users.manage') || Gate::check('access.manage')],
            ['Roles & Permissions', 'admin.roles.index', 'bi-shield-lock', 'admin.roles.*', Gate::check('roles.manage')],
            ['Global Settings', 'admin.options.index', 'bi-sliders', 'admin.options.*', Gate::check('settings.manage')],
            ['Document Templates', 'admin.templates.index', 'bi-file-earmark-richtext', 'admin.templates.*', Gate::check('settings.manage')],
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
