<x-layouts.app title="Users" :breadcrumbs="['Administration' => null, 'Users' => null]">
    <x-page-header title="Users" subtitle="Roles define what a user may do; access rules define where.">
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> New user</a>
        </x-slot:actions>
    </x-page-header>

    <form class="card card-body mb-3 py-2" method="GET">
        <div class="row g-2">
            <div class="col-md-4"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Name or email…"></div>
            <div class="col-md-3">
                <select name="role_id" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All roles</option>
                    @foreach ($roles as $id => $name)<option value="{{ $id }}" @selected(request('role_id') == $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="active" class="form-select form-select-sm" data-autosubmit>
                    <option value="">Any status</option>
                    <option value="1" @selected(request('active') === '1')>Active</option>
                    <option value="0" @selected(request('active') === '0')>Deactivated</option>
                </select>
            </div>
            <div class="col-md-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
        </div>
    </form>

    <div class="card"><div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th class="text-end">Access rules</th><th>Last login</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr>
                    <td class="fw-semibold">{{ $u->name }}@if ($u->id === auth()->id()) <span class="badge bg-light text-dark">you</span>@endif</td>
                    <td class="small">{{ $u->email }}</td>
                    <td><span class="badge bg-{{ $u->role?->key === 'super_admin' ? 'danger' : 'secondary' }}">{{ $u->role?->name ?? 'No role' }}</span></td>
                    <td>@if ($u->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-dark">Deactivated</span>@endif</td>
                    <td class="text-end">{{ $u->access_rules_count }}</td>
                    <td class="small text-muted">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                    <td class="text-end text-nowrap">
                        @can('access.manage')<a href="{{ route('admin.users.access', $u) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-shield-lock"></i> Access</a>@endcan
                        @if ($u->role?->key !== 'super_admin' || auth()->user()->isSuperAdmin())
                            <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $users->links() }}</div>
</x-layouts.app>
