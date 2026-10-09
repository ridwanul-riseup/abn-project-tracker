<x-layouts.guest title="Log in">
    <h2 class="h5 mb-3">Log in</h2>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <x-form.input name="email" label="Email" type="email" required autofocus autocomplete="username" />
        <x-form.input name="password" label="Password" type="password" required autocomplete="current-password" />
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
            <label class="form-check-label" for="remember">Remember me</label>
        </div>
        <button type="submit" class="btn btn-primary w-100">Log in</button>
    </form>
    <div class="text-center mt-3">
        <a class="small" href="{{ route('password.request') }}">Forgot your password?</a>
    </div>
    <p class="text-muted small text-center mt-3 mb-0">Accounts are created by an administrator.</p>
</x-layouts.guest>
