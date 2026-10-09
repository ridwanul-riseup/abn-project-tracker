<?php

// Dynamic configuration: option defaults, propagation of option changes,
// document templates and custom roles — all managed from the admin panel.

use App\Models\ActivityLog;
use App\Models\DocumentTemplate;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Support\Access\AccessService;
use Database\Seeders\RolesAndPermissionsSeeder;

function selectedOption(int $id): string
{
    return '<option value="'.$id.'" selected>';
}

function makeTask(int $productId, array $attributes = []): Task
{
    return Task::query()->create([
        'key' => 'OTT-'.(Task::query()->count() + 1), 'number' => Task::query()->count() + 1,
        'title' => 'Existing task', 'product_id' => $productId,
        'type_id' => optionId('task_type', 'feature'), 'status_id' => optionId('task_status', 'todo'),
        'priority_id' => optionId('priority', 'low'), ...$attributes,
    ]);
}

it('adds an option that appears in every form that uses its group', function () {
    $this->actingAs(superAdmin());
    makeProduct('OTT');
    $priority = OptionGroup::query()->where('key', 'priority')->firstOrFail();

    $this->post(route('admin.options.store', $priority), ['label' => 'Urgent'])->assertSessionHasNoErrors();

    $this->get(route('tasks.create'))->assertSee('Urgent');
    $this->get(route('modules.create'))->assertSee('Urgent');
    $this->get(route('tasks.index'))->assertSee('Urgent');
});

it('renames an option everywhere without touching the stored references', function () {
    $this->actingAs(superAdmin());
    $product = makeProduct('OTT');
    $low = Option::query()->findOrFail(optionId('priority', 'low'));
    $task = makeTask($product->id);

    $this->put(route('admin.options.update', $low), ['label' => 'Can wait'])->assertSessionHasNoErrors();

    expect($task->fresh()->priority_id)->toBe($low->id)->and($low->fresh()->value)->toBe('low');
    $this->get(route('tasks.show', $task))->assertSee('Can wait')->assertDontSee('>Low<', false);
    $this->get(route('tasks.index'))->assertSee('Can wait');
});

it('preselects the administrator-chosen default on create forms only', function () {
    $this->actingAs(superAdmin());
    $product = makeProduct('OTT');
    $high = optionId('priority', 'high');
    $medium = optionId('priority', 'medium');
    $active = optionId('product_status', 'active');

    // Seeded default reproduces the previous behaviour.
    $this->get(route('tasks.create'))->assertSee(selectedOption($medium), false);

    $this->post(route('admin.options.default', $high))->assertSessionHasNoErrors();
    $this->post(route('admin.options.default', $active))->assertSessionHasNoErrors();

    expect(Option::query()->findOrFail($medium)->is_default)->toBeFalse()
        ->and(Option::query()->findOrFail($high)->is_default)->toBeTrue();

    $this->get(route('tasks.create'))->assertSee(selectedOption($high), false);
    $this->get(route('modules.create'))->assertSee(selectedOption($high), false);
    $this->get(route('products.create'))->assertSee(selectedOption($active), false);

    // Editing an existing record never silently fills an empty field.
    $product->update(['status_id' => null]);
    $this->get(route('products.edit', $product))->assertDontSee(selectedOption($active), false);

    // Clearing the default leaves the field empty again.
    $this->post(route('admin.options.default', $active))->assertSessionHasNoErrors();
    $this->get(route('products.create'))->assertDontSee(selectedOption($active), false);
});

it('starts new tasks in the configured default status and never in an inactive one', function () {
    $this->actingAs(superAdmin());
    $product = makeProduct('OTT');
    $payload = ['title' => 'New work', 'product_id' => $product->id, 'type_id' => optionId('task_type', 'bug'), 'priority_id' => optionId('priority', 'low')];

    $todo = optionId('task_status', 'todo');
    $this->post(route('admin.options.default', $todo))->assertSessionHasNoErrors();
    $this->post(route('tasks.store'), $payload)->assertSessionHasNoErrors();
    expect(Task::query()->latest('id')->first()->status_id)->toBe($todo);

    // Deactivating the default clears it; tasks fall back to the first active open status.
    $backlog = optionId('task_status', 'backlog');
    $this->post(route('admin.options.toggle', $backlog));
    $this->post(route('admin.options.toggle', $todo));
    expect(Option::query()->findOrFail($todo)->is_default)->toBeFalse();

    $this->post(route('tasks.store'), [...$payload, 'title' => 'Second'])->assertSessionHasNoErrors();
    expect(Task::query()->latest('id')->first()->status_id)->toBe(optionId('task_status', 'in_progress'));
});

it('only allows active options, and open statuses, to become defaults', function () {
    $this->actingAs(superAdmin());
    $done = optionId('task_status', 'done');
    $low = Option::query()->findOrFail(optionId('priority', 'low'));
    $low->update(['is_active' => false]);

    $this->post(route('admin.options.default', $done))->assertSessionHasErrors('option');
    $this->post(route('admin.options.default', $low))->assertSessionHasErrors('option');

    // Changing the default status to a closed behavior clears the default.
    $backlog = Option::query()->findOrFail(optionId('task_status', 'backlog'));
    expect($backlog->is_default)->toBeTrue();
    $this->put(route('admin.options.update', $backlog), ['label' => 'Backlog', 'behavior' => 'closed'])->assertSessionHasNoErrors();
    expect($backlog->fresh()->is_default)->toBeFalse();
});

it('shows options in the administrator-defined order and audits reordering', function () {
    $this->actingAs(superAdmin());
    makeProduct('OTT');
    $critical = Option::query()->findOrFail(optionId('priority', 'critical'));

    foreach (range(1, 3) as $i) {
        $this->post(route('admin.options.move', [$critical, 'up']));
    }

    $this->get(route('tasks.create'))->assertSeeInOrder(['Critical', 'Low', 'Medium', 'High']);
    expect(ActivityLog::query()->where('action', 'option.reordered')->count())->toBe(3);
});

it('rejects invalid, foreign-group and inactive option IDs submitted directly', function () {
    $this->actingAs(superAdmin());
    $product = makeProduct('OTT');
    $base = ['title' => 'Direct', 'product_id' => $product->id, 'type_id' => optionId('task_type', 'bug'), 'priority_id' => optionId('priority', 'low')];
    $high = Option::query()->findOrFail(optionId('priority', 'high'));
    $high->update(['is_active' => false]);

    $this->post(route('tasks.store'), [...$base, 'priority_id' => $high->id])->assertSessionHasErrors('priority_id');
    $this->post(route('tasks.store'), [...$base, 'priority_id' => optionId('criticality', 'low')])->assertSessionHasErrors('priority_id');
    $this->post(route('tasks.store'), [...$base, 'priority_id' => 999999])->assertSessionHasErrors('priority_id');
    $this->post(route('tasks.store'), [...$base, 'priority_id' => 'abc'])->assertSessionHasErrors('priority_id');
    $this->put(route('products.update', $product), ['code' => 'OTT', 'name' => $product->name, 'slug' => $product->slug, 'status_id' => optionId('task_status', 'done')])
        ->assertSessionHasErrors('status_id');

    expect(Task::query()->count())->toBe(0);
});

it('lets administrators manage document templates used by the document editor', function () {
    $this->actingAs(superAdmin());
    makeProduct('OTT', ['name' => 'ABN+ OTT']);
    $runbook = optionId('document_category', 'troubleshooting');

    $this->post(route('admin.templates.store'), [
        'name' => 'Release Runbook', 'category_id' => $runbook,
        'content' => "# {{title}}\n\nProduct: {{product}}\n\n## Rollback steps\n",
    ])->assertRedirect(route('admin.templates.index'));
    $template = DocumentTemplate::query()->where('key', 'release_runbook')->firstOrFail();

    $this->get(route('docs.create'))->assertSee('Release Runbook');
    $this->get(route('docs.create', ['template' => 'release_runbook', 'title' => 'Go-live']))
        ->assertSee('# Go-live')->assertSee('## Rollback steps')->assertSee(selectedOption($runbook), false);

    // Edit propagates; the key is immutable.
    $this->put(route('admin.templates.update', $template), ['name' => 'Runbook', 'key' => 'hacked', 'content' => "# {{title}}\n\n## Steps\n"])
        ->assertSessionHasNoErrors();
    expect($template->fresh()->key)->toBe('release_runbook');
    $this->get(route('docs.create', ['template' => 'release_runbook']))->assertSee('## Steps');

    // Inactive templates are not offered and cannot be requested directly.
    $this->post(route('admin.templates.toggle', $template));
    $this->get(route('docs.create'))->assertDontSee('>Runbook<', false);
    $this->get(route('docs.create', ['template' => 'release_runbook']))->assertDontSee('## Steps');

    // Secrets are rejected; deletion works.
    $this->post(route('admin.templates.store'), ['name' => 'Leaky', 'content' => 'key AKIAABCDEFGHIJKLMNOP'])->assertSessionHasErrors('content');
    $this->delete(route('admin.templates.destroy', $template))->assertRedirect();
    expect(DocumentTemplate::query()->whereKey($template->id)->exists())->toBeFalse()
        ->and(ActivityLog::query()->where('subject_type', 'document_template')->exists())->toBeTrue();
});

it('falls back to a title-only document when no template is active', function () {
    $this->actingAs(superAdmin());
    DocumentTemplate::query()->update(['is_active' => false]);

    $this->get(route('docs.create', ['template' => 'overview', 'title' => 'Fallback']))
        ->assertOk()->assertSee('# Fallback')->assertDontSee('Start from template');
});

it('blocks deleting a category that a template still uses', function () {
    $this->actingAs(superAdmin());
    $category = Option::query()->findOrFail(optionId('document_category', 'glossary'));
    DocumentTemplate::query()->firstOrFail()->update(['category_id' => $category->id]);

    $this->delete(route('admin.options.destroy', $category))->assertSessionHas('error');
    expect(Option::query()->whereKey($category->id)->exists())->toBeTrue();
});

it('lets administrators create, rename and delete custom roles', function () {
    $admin = superAdmin();
    $this->actingAs($admin);

    $this->post(route('admin.roles.store'), ['name' => 'Release Manager', 'description' => 'Coordinates releases'])->assertSessionHasNoErrors();
    $role = Role::query()->where('key', 'release_manager')->firstOrFail();
    expect($role->is_system)->toBeFalse()->and($role->permissions()->count())->toBe(0);

    // Capabilities can be assigned in the matrix and take effect.
    $this->put(route('admin.roles.update'), ['capabilities' => [$role->id => ['tasks.comment']]])->assertSessionHasNoErrors();
    $this->get(route('admin.users.create'))->assertSee('Release Manager');
    $user = userWithRole('release_manager');
    expect(app(AccessService::class)->hasCapability($user->fresh(), 'tasks.comment'))->toBeTrue();

    // Rename keeps the key; system roles can be renamed but not deleted.
    $this->put(route('admin.roles.details', $role), ['name' => 'Release Lead'])->assertSessionHasNoErrors();
    expect($role->fresh())->name->toBe('Release Lead')->key->toBe('release_manager');
    $viewer = Role::query()->where('key', Role::VIEWER)->firstOrFail();
    $this->put(route('admin.roles.details', $viewer), ['name' => 'Read-only'])->assertSessionHasNoErrors();
    $this->delete(route('admin.roles.destroy', $viewer))->assertSessionHas('error');
    $this->put(route('admin.roles.details', Role::query()->where('key', Role::SUPER_ADMIN)->firstOrFail()), ['name' => 'God'])->assertForbidden();

    // A role in use cannot be deleted; once unused it can.
    $this->delete(route('admin.roles.destroy', $role))->assertSessionHas('error');
    $user->update(['role_id' => $viewer->id]);
    $this->delete(route('admin.roles.destroy', $role))->assertSessionHas('status');
    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse()
        ->and(ActivityLog::query()->whereIn('action', ['role.created', 'role.updated', 'role.deleted'])->count())->toBe(4);

    // Re-seeding does not undo the rename of a system role.
    $this->seed(RolesAndPermissionsSeeder::class);
    expect($viewer->fresh()->name)->toBe('Read-only');
});

it('denies every configuration change to users without the capability', function () {
    $manager = userWithRole(Role::PROJECT_ADMIN, [[null, 'write']]);
    $option = Option::query()->findOrFail(optionId('priority', 'high'));
    $template = DocumentTemplate::query()->firstOrFail();
    $role = Role::query()->where('key', Role::VIEWER)->firstOrFail();

    $this->actingAs($manager);
    $this->post(route('admin.options.default', $option))->assertForbidden();
    $this->post(route('admin.options.move', [$option, 'up']))->assertForbidden();
    $this->get(route('admin.templates.index'))->assertForbidden();
    $this->get(route('admin.templates.create'))->assertForbidden();
    $this->post(route('admin.templates.store'), ['name' => 'X', 'content' => 'x'])->assertForbidden();
    $this->put(route('admin.templates.update', $template), ['name' => 'X', 'content' => 'x'])->assertForbidden();
    $this->post(route('admin.templates.toggle', $template))->assertForbidden();
    $this->delete(route('admin.templates.destroy', $template))->assertForbidden();
    $this->post(route('admin.roles.store'), ['name' => 'Sneaky'])->assertForbidden();
    $this->put(route('admin.roles.details', $role), ['name' => 'Sneaky'])->assertForbidden();
    $this->delete(route('admin.roles.destroy', $role))->assertForbidden();

    expect($option->fresh()->is_default)->toBeFalse()
        ->and($template->fresh()->is_active)->toBeTrue()
        ->and($role->fresh()->name)->toBe('Viewer')
        ->and(Role::query()->where('name', 'Sneaky')->exists())->toBeFalse()
        ->and(User::query()->count())->toBe(1);
});

it('keeps deactivated options available as list filters for historical records', function () {
    $this->actingAs(superAdmin());
    $product = makeProduct('OTT');
    $research = Option::query()->findOrFail(optionId('task_type', 'research'));
    makeTask($product->id, ['title' => 'Old research task', 'type_id' => $research->id, 'status_id' => optionId('task_status', 'done')]);
    makeTask($product->id, ['title' => 'Feature task', 'status_id' => optionId('task_status', 'done')]);
    $research->update(['is_active' => false]);

    $this->get(route('tasks.index'))->assertSee('Research (inactive)');
    $this->get(route('tasks.index', ['type_id' => $research->id, 'state' => 'all']))
        ->assertSee('Old research task')->assertDontSee('Feature task');
});

it('applies configuration changes on the next request without a cache flush', function () {
    $this->actingAs(superAdmin());
    makeProduct('OTT');
    $group = OptionGroup::query()->where('key', 'task_type')->firstOrFail();

    $this->get(route('tasks.create'))->assertDontSee('Spike');
    $this->post(route('admin.options.store', $group), ['label' => 'Spike']);
    $this->get(route('tasks.create'))->assertSee('Spike');

    $spike = Option::query()->where('value', 'spike')->firstOrFail();
    $this->post(route('admin.options.toggle', $spike));
    $this->get(route('tasks.create'))->assertDontSee('Spike');
});
