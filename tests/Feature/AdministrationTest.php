<?php

// Workflow 1: administration.

use App\Models\AccessRule;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\AccessService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('lets an administrator log in', function () {
    $admin = superAdmin();

    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($admin);
    $this->get('/')->assertOk()->assertSee('Dashboard');
});

it('rejects wrong passwords and deactivated accounts', function () {
    $admin = superAdmin();
    $inactive = userWithRole(Role::VIEWER, attributes: ['is_active' => false]);

    $this->post('/login', ['email' => $admin->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->post('/login', ['email' => $inactive->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('throttles repeated failed logins', function () {
    $admin = superAdmin();
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => $admin->email, 'password' => 'wrong']);
    }

    $response = $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toStartWith('Too many login attempts.');
    $this->assertGuest();
});

it('lets an active user reset a forgotten password and never sends links to inactive accounts', function () {
    Notification::fake();
    $user = userWithRole(Role::VIEWER);
    $inactive = userWithRole(Role::VIEWER, attributes: ['is_active' => false]);

    $message = 'If an active account exists for that address, a password reset link has been sent.';
    $this->post(route('password.email'), ['email' => $inactive->email])->assertSessionHas('status', $message);
    $this->post(route('password.email'), ['email' => 'nobody@example.test'])->assertSessionHas('status', $message);
    Notification::assertNothingSent();

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status', $message);

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $this->post(route('password.update'), [
        'token' => 'invalid-token', 'email' => $user->email,
        'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
    ])->assertSessionHasErrors('email');

    $this->post(route('password.update'), [
        'token' => $token, 'email' => $user->email,
        'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
    ])->assertRedirect(route('login'));

    $this->post('/login', ['email' => $user->email, 'password' => 'new-password-123'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('lets users change their name and password but not their role', function () {
    $user = userWithRole(Role::VIEWER);
    $viewerRoleId = $user->role_id;

    $this->actingAs($user)->put(route('profile.update'), [
        'name' => 'Renamed Viewer', 'role_id' => Role::query()->where('key', Role::SUPER_ADMIN)->value('id'),
    ])->assertRedirect();
    expect($user->fresh())->name->toBe('Renamed Viewer')->role_id->toBe($viewerRoleId);

    $this->put(route('profile.password'), [
        'current_password' => 'wrong', 'password' => 'another-pass-123', 'password_confirmation' => 'another-pass-123',
    ])->assertSessionHasErrors('current_password');

    $this->put(route('profile.password'), [
        'current_password' => 'password', 'password' => 'another-pass-123', 'password_confirmation' => 'another-pass-123',
    ])->assertSessionHasNoErrors();
    expect(Hash::check('another-pass-123', $user->fresh()->password))->toBeTrue();
});

it('lets an administrator create a user, assign a role, grant and revoke product access, and deactivate the user', function () {
    Notification::fake();
    $admin = superAdmin();
    $product = makeProduct('OTT');
    $developerRole = Role::query()->where('key', Role::DEVELOPER)->firstOrFail();
    $managerRole = Role::query()->where('key', Role::PROJECT_MANAGER)->firstOrFail();

    // Create (no password given -> setup link is emailed).
    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Dana Developer',
        'email' => 'dana@example.test',
        'role_id' => $developerRole->id,
    ])->assertRedirect();
    $user = User::query()->where('email', 'dana@example.test')->firstOrFail();
    expect($user->role_id)->toBe($developerRole->id)->and($user->is_active)->toBeTrue();
    Notification::assertSentTo($user, ResetPassword::class);

    // Assign another role.
    $this->put(route('admin.users.update', $user), [
        'name' => 'Dana Developer', 'email' => 'dana@example.test', 'role_id' => $managerRole->id,
    ])->assertRedirect(route('admin.users.index'));
    expect($user->fresh()->role_id)->toBe($managerRole->id);

    // Grant product access.
    $this->post(route('admin.users.access.store', $user), [
        'scope_type' => 'product', 'scope_id' => $product->id, 'permission' => 'read', 'reason' => 'Joins OTT team',
    ])->assertSessionHasNoErrors();
    $rule = AccessRule::query()->where('user_id', $user->id)->firstOrFail();
    expect(app(AccessService::class)->canRead($user->fresh(), $product))->toBeTrue();

    // Revoke product access.
    $this->post(route('admin.access-rules.revoke', $rule))->assertRedirect();
    app(AccessService::class)->flush();
    expect($rule->fresh()->revoked_at)->not->toBeNull()
        ->and(app(AccessService::class)->canRead($user->fresh(), $product))->toBeFalse();

    // Deactivate.
    $this->post(route('admin.users.deactivate', $user))->assertRedirect();
    expect($user->fresh()->is_active)->toBeFalse();

    // Everything was audited.
    expect(ActivityLog::query()->pluck('action')->all())
        ->toContain('user.created', 'user.updated', 'access.granted', 'access.revoked');
});

it('signs out a deactivated user on their next request', function () {
    $user = userWithRole(Role::VIEWER);
    $this->actingAs($user)->get('/')->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->get('/')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('prevents administrator lock-out', function () {
    $admin = superAdmin();

    // Cannot deactivate yourself.
    $this->actingAs($admin)->post(route('admin.users.deactivate', $admin))->assertSessionHas('error');
    expect($admin->fresh()->is_active)->toBeTrue();

    // Cannot change your own role.
    $this->put(route('admin.users.update', $admin), [
        'name' => $admin->name, 'email' => $admin->email,
        'role_id' => Role::query()->where('key', Role::VIEWER)->value('id'),
    ])->assertSessionHasErrors('role_id');

    // Super Admins cannot be denied access.
    $other = superAdmin();
    $this->post(route('admin.users.access.store', $other), [
        'scope_type' => 'product', 'scope_id' => makeProduct('SHR')->id, 'permission' => 'deny',
    ])->assertSessionHas('error');
    expect(AccessRule::query()->where('user_id', $other->id)->count())->toBe(0);

    // No application-wide deny.
    $viewer = userWithRole(Role::VIEWER);
    $this->post(route('admin.users.access.store', $viewer), ['scope_type' => 'global', 'permission' => 'deny'])
        ->assertSessionHasErrors('permission');
});

it('does not let a non-super-admin user manager touch super admins or grant the super admin role', function () {
    $role = Role::query()->where('key', Role::PROJECT_ADMIN)->firstOrFail();
    $role->permissions()->attach(Permission::query()->where('key', 'users.manage')->value('id'));
    $manager = userWithRole(Role::PROJECT_ADMIN);
    $root = superAdmin();

    $this->actingAs($manager)->post(route('admin.users.deactivate', $root))->assertForbidden();
    $this->get(route('admin.users.edit', $root))->assertForbidden();

    $this->post(route('admin.users.store'), [
        'name' => 'Sneaky', 'email' => 'sneaky@example.test',
        'role_id' => Role::query()->where('key', Role::SUPER_ADMIN)->value('id'),
        'password' => 'long-enough-password', 'password_confirmation' => 'long-enough-password',
    ])->assertSessionHasErrors('role_id');
    expect(User::query()->where('email', 'sneaky@example.test')->exists())->toBeFalse();
});

it('forbids administration pages for non-administrators', function () {
    $user = userWithRole(Role::PROJECT_MANAGER);

    $this->actingAs($user);
    $this->get(route('admin.users.index'))->assertForbidden();
    $this->get(route('admin.roles.index'))->assertForbidden();
    $this->get(route('admin.options.index'))->assertForbidden();
    $this->get(route('admin.activity.index'))->assertForbidden();
    $this->post(route('admin.users.access.store', $user), ['scope_type' => 'global', 'permission' => 'write'])->assertForbidden();
    expect(AccessRule::query()->count())->toBe(0);
});

it('lets super admins change role capabilities and records it', function () {
    $admin = superAdmin();
    $viewer = Role::query()->where('key', Role::VIEWER)->firstOrFail();

    $this->actingAs($admin)->put(route('admin.roles.update'), [
        'capabilities' => [$viewer->id => ['tasks.comment']],
    ])->assertRedirect();

    expect($viewer->permissions()->pluck('key')->all())->toBe(['tasks.comment'])
        ->and(ActivityLog::query()->where('action', 'role.capabilities_changed')->exists())->toBeTrue();
});
