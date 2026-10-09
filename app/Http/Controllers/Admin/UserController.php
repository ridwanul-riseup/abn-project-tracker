<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * User administration. Lock-out protections:
 *  - users cannot deactivate themselves or change their own role;
 *  - the last active Super Admin can be neither deactivated nor demoted;
 *  - only Super Admins can grant the Super Admin role or manage Super Admin accounts.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('users.manage');

        $query = User::query()->with('role')->withCount(['accessRules' => fn ($q) => $q->whereNull('revoked_at')]);
        if ($q = $request->string('q')->trim()->toString()) {
            $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
        }
        if ($roleId = $request->integer('role_id')) {
            $query->where('role_id', $roleId);
        }
        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return view('admin.users.index', [
            'users' => $query->orderBy('name')->paginate($this->perPage())->withQueryString(),
            'roles' => Role::query()->orderBy('id')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('users.manage');

        return view('admin.users.form', ['user' => new User(['is_active' => true]), 'roles' => $this->assignableRoles()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('users.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', Rule::in($this->assignableRoles()->keys()->all())],
            'password' => ['nullable', 'confirmed', PasswordRule::defaults()],
        ]);

        $sendLink = empty($validated['password']);
        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'role_id' => (int) $validated['role_id'],
            'password' => $validated['password'] ?? Str::password(32),
            'is_active' => true,
        ]);

        if ($sendLink) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return redirect()->route('admin.users.access', $user)->with('status', 'User created.'
            .($sendLink ? ' A password setup link was sent to their email address.' : '')
            .' Grant access to products or other resources below.');
    }

    public function edit(User $user): View
    {
        $this->authorizeManage($user);

        return view('admin.users.form', ['user' => $user, 'roles' => $this->assignableRoles()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role_id' => ['required', Rule::in($this->assignableRoles()->keys()->all())],
        ]);
        $roleId = (int) $validated['role_id'];

        if ($roleId !== $user->role_id) {
            if ($user->id === $this->user()->id) {
                throw ValidationException::withMessages(['role_id' => 'You cannot change your own role.']);
            }
            if ($user->isSuperAdmin() && $this->isLastActiveSuperAdmin($user)) {
                throw ValidationException::withMessages(['role_id' => 'This is the last active Super Admin and cannot be demoted.']);
            }
        }

        $user->update([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'role_id' => $roleId,
        ]);

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    public function deactivate(User $user): RedirectResponse
    {
        $this->authorizeManage($user);

        if ($user->id === $this->user()->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }
        if ($user->isSuperAdmin() && $this->isLastActiveSuperAdmin($user)) {
            return back()->with('error', 'The last active Super Admin cannot be deactivated.');
        }

        DB::transaction(function () use ($user) {
            $user->forceFill([
                'is_active' => false,
                'deactivated_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
        });

        return back()->with('status', "{$user->name} has been deactivated and signed out. Their history is kept.");
    }

    public function activate(User $user): RedirectResponse
    {
        $this->authorizeManage($user);

        $user->forceFill(['is_active' => true, 'deactivated_at' => null])->save();

        return back()->with('status', "{$user->name} has been reactivated.");
    }

    public function sendReset(User $user): RedirectResponse
    {
        $this->authorizeManage($user);

        if (! $user->is_active) {
            return back()->with('error', 'Reactivate the user before sending a reset link.');
        }

        Password::sendResetLink(['email' => $user->email]);
        Audit::record('user.reset_link_sent', $user);

        return back()->with('status', "A password reset link was sent to {$user->email}.");
    }

    private function authorizeManage(User $target): void
    {
        Gate::authorize('users.manage');
        if ($target->role?->key === Role::SUPER_ADMIN && ! $this->access()->isSuperAdmin($this->user())) {
            abort(403, 'Only Super Admins can manage Super Admin accounts.');
        }
    }

    /**
     * @return Collection<int, string>
     */
    private function assignableRoles(): Collection
    {
        return Role::query()->orderBy('id')
            ->when(! $this->access()->isSuperAdmin($this->user()), fn ($q) => $q->where('key', '!=', Role::SUPER_ADMIN))
            ->pluck('name', 'id');
    }

    private function isLastActiveSuperAdmin(User $user): bool
    {
        return User::query()->active()
            ->whereHas('role', fn ($q) => $q->where('key', Role::SUPER_ADMIN))
            ->whereKeyNot($user->id)
            ->doesntExist();
    }
}
