<?php

// Workflow 5: global settings (configurable dropdown options).

use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Role;
use App\Models\Task;

it('lets an administrator add an option that then appears in the relevant forms', function () {
    $admin = superAdmin();
    makeProduct('OTT');
    $group = OptionGroup::query()->where('key', 'task_type')->firstOrFail();

    $this->actingAs($admin)->post(route('admin.options.store', $group), [
        'label' => 'Security Review', 'color' => 'danger', 'description' => 'Security-focused work',
    ])->assertSessionHasNoErrors();

    $option = Option::query()->where('option_group_id', $group->id)->where('value', 'security_review')->firstOrFail();
    expect($option->is_active)->toBeTrue();

    $this->get(route('tasks.create'))->assertOk()->assertSee('Security Review');
    $this->get(route('tasks.index'))->assertSee('Security Review');
});

it('deactivates an option that is in use without breaking existing records', function () {
    $admin = superAdmin();
    $product = makeProduct('OTT');
    $research = Option::query()->findOrFail(optionId('task_type', 'research'));
    $task = Task::query()->create([
        'key' => 'OTT-1', 'number' => 1, 'title' => 'Investigate DRM', 'product_id' => $product->id,
        'type_id' => $research->id, 'status_id' => optionId('task_status', 'todo'), 'priority_id' => optionId('priority', 'low'),
    ]);

    $this->actingAs($admin);

    // In use -> cannot be deleted.
    $this->delete(route('admin.options.destroy', $research))->assertSessionHas('error');
    expect(Option::query()->whereKey($research->id)->exists())->toBeTrue();

    // But can be deactivated.
    $this->post(route('admin.options.toggle', $research))->assertRedirect();
    expect($research->fresh()->is_active)->toBeFalse();

    // Existing record still shows it; new forms no longer offer it; the record can still be saved with it.
    $this->get(route('tasks.show', $task))->assertOk()->assertSee('Research');
    $this->get(route('tasks.create'))->assertDontSee('>Research<', false);
    $this->put(route('tasks.update', $task), [
        'title' => 'Investigate DRM', 'type_id' => $research->id,
        'status_id' => optionId('task_status', 'todo'), 'priority_id' => optionId('priority', 'low'),
    ])->assertSessionHasNoErrors();

    // A different task cannot newly select the inactive option.
    $this->post(route('tasks.store'), [
        'title' => 'Other', 'product_id' => $product->id, 'type_id' => $research->id, 'priority_id' => optionId('priority', 'low'),
    ])->assertSessionHasErrors('type_id');
});

it('keeps option keys immutable and requires an open task status', function () {
    $admin = superAdmin();
    $todo = Option::query()->findOrFail(optionId('task_status', 'todo'));

    $this->actingAs($admin)->put(route('admin.options.update', $todo), [
        'label' => 'Ready', 'behavior' => 'open', 'value' => 'hacked',
    ])->assertRedirect();
    expect($todo->fresh()->value)->toBe('todo')->and($todo->fresh()->label)->toBe('Ready');

    // Deactivate every open status but one; the last must stay.
    $open = Option::query()->where('option_group_id', $todo->option_group_id)->where('behavior', 'open')->get();
    foreach ($open->slice(1) as $status) {
        $this->post(route('admin.options.toggle', $status));
    }
    $this->post(route('admin.options.toggle', $open->first()))->assertSessionHasErrors('option');
    expect($open->first()->fresh()->is_active)->toBeTrue();
});

it('deletes unused options and reorders them', function () {
    $admin = superAdmin();
    $group = OptionGroup::query()->where('key', 'priority')->firstOrFail();
    $this->actingAs($admin)->post(route('admin.options.store', $group), ['label' => 'Trivial']);
    $trivial = Option::query()->where('value', 'trivial')->firstOrFail();

    $this->post(route('admin.options.move', [$trivial, 'up']))->assertRedirect();
    $order = Option::query()->where('option_group_id', $group->id)->orderBy('sort_order')->pluck('value')->all();
    expect(array_slice($order, -2))->toBe(['trivial', 'critical']);

    $this->delete(route('admin.options.destroy', $trivial))->assertSessionHas('status');
    expect(Option::query()->whereKey($trivial->id)->exists())->toBeFalse();
});

it('prevents unauthorized users from changing global settings', function () {
    $manager = userWithRole(Role::PROJECT_ADMIN, [[null, 'write']]);
    $option = Option::query()->findOrFail(optionId('priority', 'low'));
    $group = OptionGroup::query()->where('key', 'priority')->firstOrFail();

    $this->actingAs($manager);
    $this->get(route('admin.options.index'))->assertForbidden();
    $this->post(route('admin.options.store', $group), ['label' => 'Nope'])->assertForbidden();
    $this->put(route('admin.options.update', $option), ['label' => 'Nope'])->assertForbidden();
    $this->post(route('admin.options.toggle', $option))->assertForbidden();
    $this->delete(route('admin.options.destroy', $option))->assertForbidden();

    expect($option->fresh()->label)->toBe('Low')->and($option->fresh()->is_active)->toBeTrue();
});
