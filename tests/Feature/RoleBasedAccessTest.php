<?php

// Role-based access control: per-module permissions and scoped role assignments.

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Document;
use App\Models\Permission;
use App\Models\Repository;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Task;
use App\Models\User;
use App\Support\Access\AccessService;
use App\Support\Access\Capabilities;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;

function assignRole(User $user, string $roleKey, ?Model $scope = null): RoleAssignment
{
    $assignment = RoleAssignment::query()->create([
        'user_id' => $user->id,
        'role_id' => Role::query()->where('key', $roleKey)->value('id'),
        'scope_type' => $scope?->getMorphClass() ?? 'global',
        'scope_id' => $scope?->getKey(),
    ]);
    app(AccessService::class)->flush();

    return $assignment;
}

beforeEach(function () {
    $this->ott = makeProduct('OTT');
    $this->erp = makeProduct('ERP');
    $this->player = makeModule($this->ott, 'OTT-PLAYER');
    $this->billing = makeModule($this->ott, 'OTT-BILLING');
    $this->api = makeRepository($this->ott, 'OTT-API');
    $this->web = makeRepository($this->ott, 'OTT-WEB');
    $this->erpRepo = makeRepository($this->erp, 'ERP-CORE');
});

it('seeds default permissions for developer, project manager, QA and viewer roles', function () {
    $keys = fn (string $role) => Role::query()->where('key', $role)->firstOrFail()->permissions()->pluck('key')->sort()->values()->all();
    $views = Capabilities::viewPermissions();

    expect($keys(Role::VIEWER))->toEqualCanonicalizing($views)
        ->and($keys(Role::DEVELOPER))->toEqualCanonicalizing([...$views, 'docs.create', 'docs.update', 'tasks.update_assigned', 'tasks.comment'])
        ->and($keys(Role::QA_ENGINEER))->toEqualCanonicalizing([...$views, 'tasks.create', 'tasks.update_assigned', 'tasks.comment'])
        ->and($keys(Role::PROJECT_MANAGER))->toContain('tasks.assign', 'modules.update', 'repositories.create', 'docs.archive', 'clarifications.resolve')
        ->and($keys(Role::PROJECT_MANAGER))->not->toContain('products.create', 'users.manage', 'audit.view', 'repositories.view_sensitive')
        ->and($keys(Role::SUPER_ADMIN))->toEqualCanonicalizing(array_keys(Capabilities::all()));
    expect($views)->not->toContain('audit.view');
});

it('gives a module-scoped developer access to that module only', function () {
    $user = userWithRole(Role::VIEWER);
    assignRole($user, Role::DEVELOPER, $this->player);
    $this->actingAs($user);

    $this->get(route('modules.show', $this->player))->assertOk();
    $this->get(route('modules.show', $this->billing))->assertNotFound();
    $this->get(route('products.show', $this->ott))->assertNotFound();
    $this->get(route('modules.index'))->assertOk()->assertSee('OTT-PLAYER')->assertDontSee('OTT-BILLING');

    // The Developer role may write documents in the module, but not edit the module itself.
    $this->get(route('modules.edit', $this->player))->assertForbidden();
    $this->post(route('docs.store'), [
        'title' => 'Player notes', 'category_id' => optionId('document_category', 'overview'),
        'module_id' => $this->player->id, 'visibility' => 'inherit', 'content' => "# Player\n",
    ])->assertRedirect();
    $this->post(route('docs.store'), [
        'title' => 'Billing notes', 'category_id' => optionId('document_category', 'overview'),
        'module_id' => $this->billing->id, 'visibility' => 'inherit', 'content' => "# Billing\n",
    ])->assertSessionHasErrors('module_id');

    expect(Document::query()->pluck('title')->all())->toBe(['Player notes']);
});

it('lets a QA engineer assigned to a module report tasks there without product access', function () {
    $user = userWithRole(Role::VIEWER);
    assignRole($user, Role::QA_ENGINEER, $this->player);

    $this->actingAs($user)->post(route('tasks.store'), [
        'title' => 'Seek bar freezes', 'product_id' => $this->ott->id, 'module_id' => $this->player->id,
        'type_id' => optionId('task_type', 'bug'), 'priority_id' => optionId('priority', 'high'),
    ])->assertRedirect();
    $this->post(route('tasks.store'), [
        'title' => 'Product-level task', 'product_id' => $this->ott->id,
        'type_id' => optionId('task_type', 'bug'), 'priority_id' => optionId('priority', 'high'),
    ])->assertSessionHasErrors('product_id');

    expect(Task::query()->pluck('title')->all())->toBe(['Seek bar freezes']);
});

it('lets a repository-scoped project manager manage that repository and its tasks only', function () {
    $user = userWithRole(Role::DEVELOPER, [[$this->ott, 'read']]);
    assignRole($user, Role::PROJECT_MANAGER, $this->api);
    $developer = userWithRole(Role::DEVELOPER, [[$this->ott, 'read']]);
    $apiTask = makeTask($this->ott->id, ['repository_id' => $this->api->id]);
    $webTask = makeTask($this->ott->id, ['repository_id' => $this->web->id]);
    $access = app(AccessService::class);

    expect(Gate::forUser($user)->allows('update', $this->api))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $this->web))->toBeFalse()
        ->and(Gate::forUser($user)->allows('update', $this->ott))->toBeFalse()
        ->and(Gate::forUser($user)->allows('assign', $apiTask))->toBeTrue()
        ->and(Gate::forUser($user)->allows('assign', $webTask))->toBeFalse()
        ->and($access->level($user, $this->api))->toBe(AccessService::WRITE)
        ->and($access->level($user, $this->web))->toBe(AccessService::READ);

    $this->actingAs($user)->put(route('tasks.update', $apiTask), [
        'title' => 'Assigned by repo lead', 'type_id' => $apiTask->type_id, 'status_id' => $apiTask->status_id,
        'priority_id' => $apiTask->priority_id, 'repository_id' => $this->api->id, 'assignee_id' => $developer->id,
    ])->assertRedirect();
    $this->put(route('tasks.update', $webTask), ['title' => 'Nope'])->assertForbidden();

    expect($apiTask->fresh()->assignee_id)->toBe($developer->id)->and($webTask->fresh()->title)->toBe('Existing task');
});

it('lets an application-scoped project manager add repositories to that application only', function () {
    $app = Application::query()->create(['code' => 'OTT-APP', 'name' => 'Player app', 'product_id' => $this->ott->id]);
    $user = userWithRole(Role::VIEWER);
    assignRole($user, Role::PROJECT_MANAGER, $app);
    $this->actingAs($user);

    $this->get(route('repositories.create', ['product_id' => $this->ott->id, 'application_id' => $app->id]))->assertOk();
    $this->post(route('repositories.store'), ['code' => 'OTT-APP-IOS', 'name' => 'ios', 'product_id' => $this->ott->id, 'application_id' => $app->id])
        ->assertRedirect();
    $this->post(route('repositories.store'), ['code' => 'OTT-LOOSE', 'name' => 'loose', 'product_id' => $this->ott->id])
        ->assertForbidden();

    expect(Repository::query()->where('code', 'OTT-APP-IOS')->value('application_id'))->toBe($app->id)
        ->and(Repository::query()->where('code', 'OTT-LOOSE')->exists())->toBeFalse();
});

it('applies an application-wide role assignment to every product', function () {
    $user = userWithRole(Role::VIEWER);
    assignRole($user, Role::PROJECT_MANAGER);

    expect(Gate::forUser($user)->allows('update', $this->erpRepo))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $this->player))->toBeTrue()
        ->and(Gate::forUser($user)->allows('products.create'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('users.manage'))->toBeFalse();
});

it('keeps explicit denies stronger than role assignments', function () {
    $user = userWithRole(Role::VIEWER);
    assignRole($user, Role::PROJECT_MANAGER, $this->ott);
    grantAccess($user, $this->api, 'deny');
    app(AccessService::class)->flush();

    $this->actingAs($user)->get(route('repositories.show', $this->web))->assertOk();
    $this->get(route('repositories.show', $this->api))->assertNotFound();
});

it('hides a feature module when the role lacks its view permission', function () {
    $user = userWithRole(Role::VIEWER, [[$this->ott, 'read']]);
    $viewer = Role::query()->where('key', Role::VIEWER)->firstOrFail();
    $viewer->permissions()->detach(Permission::query()->where('key', 'repositories.view')->value('id'));
    app(AccessService::class)->flush();

    $this->actingAs($user);
    $this->get(route('products.show', $this->ott))->assertOk();
    $this->get(route('repositories.index'))->assertForbidden();
    $this->get(route('repositories.show', $this->api))->assertNotFound();
    $this->get('/')->assertOk()->assertDontSee(route('repositories.index'));
});

it('removes scoped role permissions as soon as the assignment is revoked, and audits both steps', function () {
    $admin = superAdmin();
    $user = userWithRole(Role::VIEWER);
    $developer = Role::query()->where('key', Role::DEVELOPER)->firstOrFail();

    $this->actingAs($admin)->post(route('admin.users.roles.store', $user), [
        'role_id' => $developer->id, 'assignment_scope_type' => 'module', 'assignment_scope_id' => $this->player->id, 'reason' => 'Player squad',
    ])->assertSessionHasNoErrors();
    $assignment = RoleAssignment::query()->where('user_id', $user->id)->firstOrFail();
    expect(app(AccessService::class)->canRead($user->fresh(), $this->player))->toBeTrue();
    $this->get(route('admin.users.access', $user))->assertOk()->assertSee('Developer')->assertSee('OTT-PLAYER');

    $this->post(route('admin.role-assignments.revoke', $assignment))->assertRedirect();
    app(AccessService::class)->flush();

    expect($assignment->fresh()->revoked_at)->not->toBeNull()
        ->and(app(AccessService::class)->canRead($user->fresh(), $this->player))->toBeFalse()
        ->and(ActivityLog::query()->whereIn('action', ['role.assigned', 'role.unassigned'])->count())->toBe(2);
});

it('blocks privilege escalation through role assignments', function () {
    $managerRole = Role::query()->create(['key' => 'access_manager', 'name' => 'Access manager']);
    $managerRole->permissions()->sync(Permission::query()->whereIn('key', ['access.manage'])->pluck('id'));
    $manager = userWithRole('access_manager');
    $target = userWithRole(Role::VIEWER);
    $superRole = Role::query()->where('key', Role::SUPER_ADMIN)->firstOrFail();
    $this->actingAs($manager);

    // Roles with administration permissions cannot be given application-wide by non-Super Admins.
    $this->post(route('admin.users.roles.store', $target), ['role_id' => $managerRole->id, 'assignment_scope_type' => 'global'])
        ->assertSessionHasErrors('role_id');
    // Super Admin is never assignable, and nobody but a Super Admin can assign roles to themselves.
    $this->post(route('admin.users.roles.store', $target), ['role_id' => $superRole->id, 'assignment_scope_type' => 'global'])
        ->assertSessionHasErrors('role_id');
    $this->post(route('admin.users.roles.store', $manager), [
        'role_id' => Role::query()->where('key', Role::PROJECT_MANAGER)->value('id'), 'assignment_scope_type' => 'global',
    ])->assertSessionHas('error');
    // Users without access.manage cannot assign roles at all.
    $this->actingAs(userWithRole(Role::PROJECT_ADMIN))->post(route('admin.users.roles.store', $target), [
        'role_id' => Role::query()->where('key', Role::VIEWER)->value('id'), 'assignment_scope_type' => 'product', 'assignment_scope_id' => $this->ott->id,
    ])->assertForbidden();

    expect(RoleAssignment::query()->count())->toBe(0);
});

it('converts the old coarse permissions when the seeder runs on an existing install', function () {
    $legacy = Permission::query()->create(['key' => 'inventory.write', 'group' => 'Inventory', 'name' => 'Legacy']);
    $legacyDocs = Permission::query()->create(['key' => 'docs.write', 'group' => 'Documentation', 'name' => 'Legacy']);
    $custom = Role::query()->create(['key' => 'tech_writer', 'name' => 'Tech writer']);
    $custom->permissions()->sync([$legacy->id, $legacyDocs->id]);
    $viewer = Role::query()->where('key', Role::VIEWER)->firstOrFail();
    $viewer->permissions()->sync([]);

    $this->seed(RolesAndPermissionsSeeder::class);

    $keys = $custom->permissions()->pluck('key')->all();
    expect($keys)->toContain('repositories.update', 'modules.create', 'clarifications.resolve', 'docs.create', 'docs.archive', 'products.view')
        ->and($keys)->not->toContain('tasks.assign')
        ->and(Permission::query()->whereIn('key', array_keys(Capabilities::LEGACY))->exists())->toBeFalse()
        ->and($viewer->permissions()->pluck('key')->all())->toEqualCanonicalizing(Capabilities::viewPermissions());
});
