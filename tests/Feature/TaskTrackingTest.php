<?php

// Workflow 4: development tracking.

use App\Models\Role;
use App\Models\Task;
use App\Models\TaskComment;

beforeEach(function () {
    $this->ott = makeProduct('OTT');
    $this->erp = makeProduct('ERP');
    $this->repo = makeRepository($this->ott, 'OTT-PLAYER');
    $this->module = makeModule($this->ott, 'OTT-PLAYBACK', ['primary_repository_id' => $this->repo->id]);
    $this->doc = makeDocument('products/ott-product/player.md', 'Player Spec', "# Player\n", ['product_id' => $this->ott->id]);
    $this->manager = userWithRole(Role::PROJECT_MANAGER, [[$this->ott, 'write']]);
    $this->developer = userWithRole(Role::DEVELOPER, [[$this->ott, 'read']]);
});

function taskPayload(array $overrides = []): array
{
    return [
        'title' => 'Fix buffering on Android',
        'description' => 'Playback stalls after 30s.',
        'type_id' => optionId('task_type', 'bug'),
        'priority_id' => optionId('priority', 'high'),
        ...$overrides,
    ];
}

it('lets a manager create a task linked to product, repository, module and document, and assign it', function () {
    $this->actingAs($this->manager)->post(route('tasks.store'), taskPayload([
        'product_id' => $this->ott->id,
        'repository_id' => $this->repo->id,
        'module_id' => $this->module->id,
        'document_ids' => [$this->doc->id],
        'assignee_id' => $this->developer->id,
        'due_date' => '2026-12-01',
    ]))->assertRedirect();

    $task = Task::query()->firstOrFail();
    expect($task->key)->toBe('OTT-1')
        ->and($task->repository_id)->toBe($this->repo->id)
        ->and($task->module_id)->toBe($this->module->id)
        ->and($task->assignee_id)->toBe($this->developer->id)
        ->and($task->reporter_id)->toBe($this->manager->id)
        ->and($task->documents()->pluck('documents.id')->all())->toBe([$this->doc->id])
        ->and($task->status->behavior)->toBe('open');

    $this->post(route('tasks.store'), taskPayload(['product_id' => $this->ott->id, 'title' => 'Second']))->assertRedirect();
    expect(Task::query()->latest('id')->value('key'))->toBe('OTT-2');
});

it('only assigns tasks to users who can access them', function () {
    $outsider = userWithRole(Role::DEVELOPER, [[$this->erp, 'read']]);

    $this->actingAs($this->manager)->post(route('tasks.store'), taskPayload([
        'product_id' => $this->ott->id, 'assignee_id' => $outsider->id,
    ]))->assertSessionHasErrors('assignee_id');

    expect(Task::query()->count())->toBe(0);
});

it('rejects linking a repository from another product', function () {
    $erpRepo = makeRepository($this->erp, 'ERP-CORE');
    grantAccess($this->manager, $this->erp, 'read');

    $this->actingAs($this->manager)->post(route('tasks.store'), taskPayload([
        'product_id' => $this->ott->id, 'repository_id' => $erpRepo->id,
    ]))->assertSessionHasErrors('repository_id');
});

it('lets the assignee update progress but not edit the task', function () {
    $task = Task::query()->create([
        'key' => 'OTT-1', 'number' => 1, 'title' => 'Buffering', 'product_id' => $this->ott->id,
        'type_id' => optionId('task_type', 'bug'), 'status_id' => optionId('task_status', 'todo'),
        'priority_id' => optionId('priority', 'high'), 'assignee_id' => $this->developer->id,
    ]);

    $this->actingAs($this->developer);
    $this->patch(route('tasks.progress', $task), ['status_id' => optionId('task_status', 'blocked')])
        ->assertSessionHasErrors('blocker_description');
    $this->patch(route('tasks.progress', $task), [
        'status_id' => optionId('task_status', 'blocked'), 'blocker_description' => 'Waiting for CDN credentials from ops',
    ])->assertRedirect();
    expect($task->fresh()->status_id)->toBe(optionId('task_status', 'blocked'));

    $this->post(route('tasks.comments.store', $task), ['body' => 'Investigating'])->assertRedirect();
    expect(TaskComment::query()->where('task_id', $task->id)->value('body'))->toBe('Investigating');

    $this->get(route('tasks.edit', $task))->assertForbidden();
    $this->put(route('tasks.update', $task), taskPayload(['title' => 'Renamed']))->assertForbidden();

    // A developer who is not the assignee cannot update progress.
    $other = userWithRole(Role::DEVELOPER, [[$this->ott, 'read']]);
    $this->actingAs($other)->patch(route('tasks.progress', $task), ['status_id' => optionId('task_status', 'done')])->assertForbidden();
});

it('filters tasks correctly', function () {
    $make = fn (int $n, string $status, string $priority, ?int $assignee = null, string $product = 'ott') => Task::query()->create([
        'key' => strtoupper($product)."-{$n}", 'number' => $n, 'title' => "Task {$n}",
        'product_id' => $product === 'ott' ? $this->ott->id : $this->erp->id,
        'type_id' => optionId('task_type', 'feature'), 'status_id' => optionId('task_status', $status),
        'priority_id' => optionId('priority', $priority), 'assignee_id' => $assignee,
        'blocker_description' => $status === 'blocked' ? 'Blocked by API' : null,
    ]);
    $make(1, 'todo', 'high', $this->developer->id);
    $make(2, 'blocked', 'low');
    $make(3, 'done', 'medium');
    $make(4, 'todo', 'low', null, 'erp');

    $this->actingAs($this->developer);
    $this->get(route('tasks.index'))->assertSee('OTT-1')->assertSee('OTT-2')->assertDontSee('OTT-3')->assertDontSee('ERP-4');
    $this->get(route('tasks.index', ['state' => 'blocked']))->assertSee('OTT-2')->assertDontSee('OTT-1');
    $this->get(route('tasks.index', ['state' => 'closed']))->assertSee('OTT-3')->assertDontSee('OTT-1');
    $this->get(route('tasks.index', ['mine' => 1]))->assertSee('OTT-1')->assertDontSee('OTT-2');
    $this->get(route('tasks.index', ['state' => 'all', 'priority_id' => optionId('priority', 'low')]))->assertSee('OTT-2')->assertDontSee('OTT-1');
    $this->get(route('tasks.index', ['state' => 'all', 'q' => 'Task 3']))->assertSee('OTT-3')->assertDontSee('OTT-2');
});

it('lets QA engineers report bugs in readable products without assigning them', function () {
    $qa = userWithRole(Role::QA_ENGINEER, [[$this->ott, 'read']]);

    $this->actingAs($qa)->post(route('tasks.store'), taskPayload([
        'product_id' => $this->ott->id, 'assignee_id' => $this->developer->id,
    ]))->assertRedirect();

    $task = Task::query()->firstOrFail();
    expect($task->reporter_id)->toBe($qa->id)->and($task->assignee_id)->toBeNull();

    $this->post(route('tasks.store'), taskPayload(['product_id' => $this->erp->id]))->assertSessionHasErrors('product_id');
});
