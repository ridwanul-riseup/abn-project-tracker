<x-layouts.app title="Roles" :breadcrumbs="['Administration' => null, 'Roles' => null]">
    <x-page-header title="Roles and capabilities" subtitle="Capabilities decide what a role may do. Scoped access rules still decide where. Security rules themselves (deny precedence, Super Admin protection) are not configurable." />
    <form method="POST" action="{{ route('admin.roles.update') }}">
        @csrf @method('PUT')
        <div class="card"><div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Capability</th>
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
        <button class="btn btn-primary mt-3">Save capabilities</button>
    </form>
</x-layouts.app>
