<x-layouts.app title="Roles" :breadcrumbs="['Administration' => null, 'Roles' => null]">
    <x-page-header title="Roles and capabilities" subtitle="Permissions decide what a role may do, per feature module. A role applies application-wide as a user's global role, or only inside a product, application, repository or module when assigned there (Users &amp; Access). Scoped access rules decide where; deny precedence and Super Admin protection are not configurable." />

    <div class="card mb-3">
        <div class="card-header">Roles</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light"><tr><th>Name</th><th>Description</th><th>Key</th><th class="text-end">Users</th><th class="text-end">Scoped</th><th></th></tr></thead>
                <tbody>
                @foreach ($roles as $role)
                    @php $formId = 'role-'.$role->id; @endphp
                    <tr>
                        @if ($role->isSuperAdmin())
                            <td class="fw-semibold">{{ $role->name }}</td>
                            <td class="small text-muted">{{ $role->description }}</td>
                        @else
                            <td><input form="{{ $formId }}" name="name" value="{{ $role->name }}" class="form-control form-control-sm" required maxlength="100" aria-label="Role name"></td>
                            <td><input form="{{ $formId }}" name="description" value="{{ $role->description }}" class="form-control form-control-sm" maxlength="500" aria-label="Role description"></td>
                        @endif
                        <td><code class="small">{{ $role->key }}</code> @if ($role->is_system)<span class="badge bg-light text-dark border">system</span>@endif</td>
                        <td class="text-end">{{ $role->users_count }}</td>
                        <td class="text-end">{{ $role->assignments_count }}</td>
                        <td class="text-nowrap text-end">
                            @unless ($role->isSuperAdmin())
                                <form id="{{ $formId }}" method="POST" action="{{ route('admin.roles.details', $role) }}" class="d-inline">@csrf @method('PUT')<button class="btn btn-sm btn-outline-primary">Save</button></form>
                            @endunless
                            @if (! $role->is_system && $role->users_count === 0 && $role->assignments_count === 0)
                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline" data-confirm="Delete role “{{ $role->name }}”?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">
            <form method="POST" action="{{ route('admin.roles.store') }}">
                @csrf
                <div class="row g-2 align-items-end">
                    <x-form.input name="name" label="New role name" required maxlength="100" col="col-md-3" />
                    <x-form.input name="key" label="Stable key (optional)" help="Lowercase, digits and _. Cannot be changed later." maxlength="50" col="col-md-3" />
                    <x-form.input name="description" label="Description" maxlength="500" col="col-md-4" />
                    <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Add role</button></div>
                </div>
            </form>
        </div>
    </div>
    <form method="POST" action="{{ route('admin.roles.update') }}">
        @csrf @method('PUT')
        <div class="card"><div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Permission</th>
                        @foreach ($roles as $role)<th class="text-center small">{{ $role->name }}<div class="text-muted fw-normal">{{ $role->users_count }} users</div></th>@endforeach
                    </tr>
                </thead>
                <tbody>
                @foreach ($permissions as $group => $items)
                    <tr class="table-light"><td colspan="{{ $roles->count() + 1 }}" class="small fw-semibold">{{ $group }}</td></tr>
                    @foreach ($items as $permission)
                        <tr>
                            <td class="small">{{ $permission->name }}<div class="text-muted">{{ $permission->description }}</div></td>
                            @foreach ($roles as $role)
                                @php
                                    $has = $role->key === 'super_admin' || $role->permissions->contains('id', $permission->id);
                                    $locked = $role->key === 'super_admin' || ($group === 'Administration' && ! $canEditAdmin);
                                @endphp
                                <td class="text-center">
                                    @if ($locked)
                                        @if ($has)<i class="bi bi-check-lg text-success" title="Locked"></i>@else<i class="bi bi-dash text-muted" title="Locked"></i>@endif
                                    @else
                                        <input type="checkbox" class="form-check-input" name="capabilities[{{ $role->id }}][]" value="{{ $permission->key }}" @checked($has) aria-label="{{ $role->name }}: {{ $permission->name }}">
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div></div>
        <button class="btn btn-primary mt-3">Save permissions</button>
    </form>
</x-layouts.app>
