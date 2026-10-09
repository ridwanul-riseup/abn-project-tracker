<x-layouts.guest title="Forgot password">
    <h2 class="h5 mb-2">Reset your password</h2>
    <p class="text-muted small">Enter your email address and we will send you a password reset link.</p>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-form.input name="email" label="Email" type="email" required autofocus />
        <button type="submit" class="btn btn-primary w-100">Send reset link</button>
    </form>
    <div class="text-center mt-3"><a class="small" href="{{ route('login') }}">Back to log in</a></div>
</x-layouts.guest>
