@php
    $levelBadge = fn (?string $level) => match ($level) {
        'write' => '<span class="badge bg-success">Write</span>',
        'read' => '<span class="badge bg-info text-dark">Read</span>',
        default => '<span class="badge bg-light text-muted">None</span>',
    };
@endphp
<x-layouts.app :title="'Access: '.$target->name" :breadcrumbs="['Users' => route('admin.users.index'), $target->name => null, 'Access' => null]">
    <x-page-header :title="'Access for '.$target->name" :subtitle="$target->email.' · '.($target->role?->name ?? 'No role').($target->is_active ? '' : ' · DEACTIVATED')" />

    @if ($targetIsSuperAdmin)
        <div class="alert alert-info py-2">Super Admins always have full access. Scoped rules cannot be added and denials do not apply, so administrators can never be locked out.</div>
    @elseif (! $target->is_active)
        <div class="alert alert-warning py-2">This user is deactivated and has no access regardless of the rules below.</div>
    @endif

    <div class="row g-3">
        <div class="col-xl-5">
            @unless ($targetIsSuperAdmin)
                <div class="card mb-3">
                    <div class="card-header">Grant or deny access</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.users.access.store', $target) }}">
                            @csrf
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="form-label small" for="scope_type">Scope</label>
                                    <select id="scope_type" name="scope_type" class="form-select form-select-sm">
                                        @foreach (\App\Support\Access\AccessService::SCOPE_TYPES as $type)
                                            <option value="{{ $type }}" @selected(old('scope_type', 'product') === $type)>{{ $type === 'global' ? 'Application-wide' : ucfirst($type) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small" for="scope_id">Resource</label>
                                    <select id="scope_id" name="scope_id" class="form-select form-select-sm @error('scope_id') is-invalid @enderror">
                                        @foreach ($scopeChoices as $type => $items)
                                            @foreach ($items as $id => $label)
                                                <option value="{{ $id }}" data-type="{{ $type }}" @selected(old('scope_type') === $type && old('scope_id') == $id)>{{ $label }}</option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                    @error('scope_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small" for="permission">Permission</label>
                                    <select id="permission" name="permission" class="form-select form-select-sm @error('permission') is-invalid @enderror">
                                        <option value="read">Read</option>
                                        <option value="write">Write</option>
                                        <option value="deny">Deny (overrides inherited access)</option>
                                    </select>
                                    @error('permission')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small" for="reason">Reason (optional)</label>
                                    <input id="reason" name="reason" class="form-control form-control-sm" maxlength="255" value="{{ old('reason') }}">
                                </div>
                            </div>
                            <button class="btn btn-primary btn-sm mt-3">Save rule</button>
                            <p class="small text-muted mt-2 mb-0">A new rule on the same scope replaces the existing one. Product rules include its applications, repositories, modules and documents; a deny anywhere above a resource removes access to it.</p>
                        </form>
                    </div>
                </div>
            @endunless

            <div class="card mb-3">
                <div class="card-header">Active rules ({{ $activeRules->count() }})</div>
                <ul class="list-group list-group-flush small">
                    @forelse ($activeRules as $rule)
                        <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <span class="badge bg-{{ $rule->permission === 'deny' ? 'danger' : ($rule->permission === 'write' ? 'success' : 'info text-dark') }}">{{ ucfirst($rule->permission) }}</span>
                                {{ $rule->scopeLabel() }}
                                <div class="text-muted">by {{ $rule->granter?->name ?? 'system' }} · {{ $rule->created_at?->format('Y-m-d') }}@if ($rule->reason) · {{ $rule->reason }}@endif</div>
                            </div>
                            <form method="POST" action="{{ route('admin.access-rules.revoke', $rule) }}" data-confirm="Revoke this rule?">@csrf<button class="btn btn-sm btn-outline-danger">Revoke</button></form>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No active rules. The user only sees documents visible to all users.</li>
                    @endforelse
                </ul>
            </div>

            <div class="card mb-3">
                <div class="card-header">Role capabilities — {{ $target->role?->name ?? 'No role' }}</div>
                <ul class="list-group list-group-flush small">
                    @foreach ($capabilities as $key => [$group, $name])
                        <li class="list-group-item py-1">@if (in_array($key, $roleCapabilities, true))<i class="bi bi-check-lg text-success"></i>@else<i class="bi bi-x text-muted"></i>@endif {{ $name }}</li>
                    @endforeach
                </ul>
            </div>

            <div class="card">
                <div class="card-header">Grant and revoke history</div>
                <ul class="list-group list-group-flush small">
                    @forelse ($revokedRules as $rule)
                        <li class="list-group-item">
                            <s>{{ ucfirst($rule->permission) }} — {{ $rule->scopeLabel() }}</s>
                            <div class="text-muted">granted by {{ $rule->granter?->name ?? 'system' }} {{ $rule->created_at?->format('Y-m-d') }} · revoked by {{ $rule->revoker?->name ?? 'system' }} {{ $rule->revoked_at?->format('Y-m-d') }}</div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No revoked rules.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card">
                <div class="card-header">Effective access</div>
                <div class="card-body small text-muted pb-0">Computed with the same rules the application enforces.</div>
                @foreach (['product' => 'Products', 'application' => 'Applications', 'repository' => 'Repositories', 'module' => 'Modules'] as $type => $title)
                    <div class="px-3 pt-3 fw-semibold small">{{ $title }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            @foreach ($effective[$type] as $row)
                                <tr><td class="small ps-3">{{ $row['model']->code }} — {{ $row['model']->name }}</td><td class="text-end pe-3">{!! $levelBadge($row['level']) !!}</td></tr>
                            @endforeach
                        </table>
                    </div>
                @endforeach
                <div class="px-3 pt-3 fw-semibold small">Documents</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        @foreach ($documents as $row)
                            <tr><td class="small ps-3">{{ $row['model']->title }} <code class="text-muted">{{ $row['model']->path }}</code></td><td class="text-end pe-3">{!! $levelBadge($row['level']) !!}</td></tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
