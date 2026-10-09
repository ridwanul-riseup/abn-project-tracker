<x-layouts.guest title="Choose a new password">
    <h2 class="h5 mb-3">Choose a new password</h2>
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-form.input name="email" label="Email" type="email" :value="$email" required />
        <x-form.input name="password" label="New password" type="password" required autocomplete="new-password" />
        <x-form.input name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
        <button type="submit" class="btn btn-primary w-100">Reset password</button>
    </form>
</x-layouts.guest>
