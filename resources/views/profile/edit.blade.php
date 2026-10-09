<x-layouts.app title="Profile" :breadcrumbs="['Profile' => null]">
    <x-page-header title="Profile" :subtitle="$user->email.' · '.($user->role?->name ?? 'No role')" />
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">Profile</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf @method('PUT')
                        <x-form.input name="name" label="Name" :value="$user->name" required />
                        <button class="btn btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">Change password</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.password') }}">
                        @csrf @method('PUT')
                        <x-form.input name="current_password" label="Current password" type="password" required autocomplete="current-password" />
                        <x-form.input name="password" label="New password" type="password" required autocomplete="new-password" />
                        <x-form.input name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
                        <button class="btn btn-primary">Change password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
