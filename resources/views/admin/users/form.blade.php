@php $editing = $user->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$user->name : 'New user'" :breadcrumbs="['Users' => route('admin.users.index'), ($editing ? $user->name : 'New') => null]">
    <x-page-header :title="$editing ? 'Edit user' : 'New user'" :subtitle="$editing ? $user->email : null" />
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card"><div class="card-body">
                <form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}">
                    @csrf
                    @if ($editing) @method('PUT') @endif
                    <x-form.input name="name" label="Name" :value="$user->name" required maxlength="120" />
                    <x-form.input name="email" label="Email" type="email" :value="$user->email" required autocomplete="off" />
                    <x-form.select name="role_id" label="Role" :options="$roles" :selected="$user->role_id" required :placeholder="false"
                                   :help="$editing && $user->id === auth()->id() ? 'You cannot change your own role.' : null" />
                    @unless ($editing)
                        <p class="small text-muted mb-2">Leave the password empty to email the user a password setup link instead.</p>
                        <div class="row">
                            <x-form.input name="password" label="Initial password" type="password" autocomplete="new-password" col="col-md-6 mb-3" />
                            <x-form.input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" col="col-md-6 mb-3" />
                        </div>
                    @endunless
                    <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create user' }}</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-link">Cancel</a>
                </form>
            </div></div>
        </div>
        @if ($editing)
            <div class="col-lg-5">
                <div class="card"><div class="card-body">
                    <h2 class="h6">Account</h2>
                    <p class="small mb-2">Status: @if ($user->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-dark">Deactivated {{ $user->deactivated_at?->format('Y-m-d') }}</span>@endif</p>
                    <div class="d-flex flex-wrap gap-2">
                        @if ($user->is_active)
                            <form method="POST" action="{{ route('admin.users.send-reset', $user) }}">@csrf<button class="btn btn-sm btn-outline-secondary"><i class="bi bi-envelope"></i> Send password reset link</button></form>
                            @if ($user->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.deactivate', $user) }}" data-confirm="Deactivate {{ $user->name }}? They will be signed out immediately.">@csrf<button class="btn btn-sm btn-outline-danger"><i class="bi bi-person-x"></i> Deactivate</button></form>
                            @endif
                        @else
                            <form method="POST" action="{{ route('admin.users.activate', $user) }}">@csrf<button class="btn btn-sm btn-outline-success">Reactivate</button></form>
                        @endif
                    </div>
                    <p class="small text-muted mt-3 mb-0">Users are never deleted, so their history and audit trail remain intact.</p>
                </div></div>
            </div>
        @endif
    </div>
</x-layouts.app>
