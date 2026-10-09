<?php

// Progress reports: portfolio, product (module level), modules and workload.
// Every figure must respect the viewer's access and link to the matching tasks.

use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Role;

function taskLink(array $params, int $count): string
{
    return e(route('tasks.index', $params)).'">'.$count.'</a>';
}

beforeEach(function () {
    $this->ott = makeProduct('OTT');
    $this->shr = makeProduct('SHR');
    $this->player = makeModule($this->ott, 'OTT-PLAYER', ['name' => 'Video player']);
    $this->billing = makeModule($this->ott, 'OTT-BILLING', ['name' => 'Billing']);
    $this->dev = userWithRole(Role::DEVELOPER, [[$this->ott, 'read']], ['name' => 'Dana Developer']);

    $todo = optionId('task_status', 'todo');
    $review = optionId('task_status', 'in_review');
    $done = optionId('task_status', 'done');
    $blocked = optionId('task_status', 'blocked');

    makeTask($this->ott->id, ['module_id' => $this->player->id, 'status_id' => $todo, 'due_date' => now()->subDays(3), 'title' => 'Overdue player task']);
    makeTask($this->ott->id, ['module_id' => $this->player->id, 'status_id' => $review, 'assignee_id' => $this->dev->id, 'due_date' => now()->addDays(2), 'title' => 'Review player task']);
    makeTask($this->ott->id, ['module_id' => $this->player->id, 'status_id' => $done, 'due_date' => now()->subDays(10), 'title' => 'Finished player task']);
    makeTask($this->ott->id, ['module_id' => $this->billing->id, 'status_id' => $blocked, 'assignee_id' => $this->dev->id, 'title' => 'Blocked billing task']);
    makeTask($this->ott->id, ['status_id' => $todo, 'title' => 'Loose OTT task']);
    makeTask($this->shr->id, ['status_id' => $todo, 'title' => 'Secret SHR task']);
});

it('shows a portfolio overview with per-product counts per status that link to the tasks', function () {
    $todo = optionId('task_status', 'todo');

    $response = $this->actingAs(superAdmin())->get(route('reports.index'))->assertOk();
    $response->assertSee(taskLink(['product_id' => $this->ott->id, 'state' => 'all'], 5), false)
        ->assertSee(taskLink(['product_id' => $this->ott->id, 'state' => 'all', 'status_id' => $todo], 2), false)
        ->assertSee(taskLink(['product_id' => $this->shr->id, 'state' => 'all'], 1), false)
        // Overdue / due soon / unassigned ignore closed tasks (the overdue "done" task is not counted).
        ->assertSee(taskLink(['product_id' => $this->ott->id, 'due' => 'overdue'], 1), false)
        ->assertSee(taskLink(['product_id' => $this->ott->id, 'due' => 'soon'], 1), false)
        ->assertSee(taskLink(['product_id' => $this->ott->id, 'unassigned' => 1], 2), false)
        ->assertSee('20%');
});

it('breaks a product down to module level, including tasks without a module', function () {
    $review = optionId('task_status', 'in_review');

    $this->actingAs(superAdmin())->get(route('reports.product', $this->ott))
        ->assertOk()
        ->assertSee('OTT-PLAYER')->assertSee('OTT-BILLING')
        ->assertSee(taskLink(['module_id' => $this->player->id, 'state' => 'all'], 3), false)
        ->assertSee(taskLink(['module_id' => $this->player->id, 'state' => 'all', 'status_id' => $review], 1), false)
        ->assertSee(taskLink(['module_id' => $this->billing->id, 'state' => 'all'], 1), false)
        ->assertSee('Not linked to a module')
        ->assertSee(taskLink(['product_id' => $this->ott->id, 'no_module' => 1, 'state' => 'all'], 1), false)
        ->assertSee('Overdue player task')
        ->assertSee('Review player task')
        ->assertSee('Blocked billing task')
        ->assertDontSee('Secret SHR task');
});

it('drills down from report numbers to exactly the matching tasks', function () {
    $this->actingAs(superAdmin());

    $this->get(route('tasks.index', ['product_id' => $this->ott->id, 'due' => 'overdue']))
        ->assertSee('Overdue player task')->assertDontSee('Finished player task')->assertDontSee('Review player task');
    $this->get(route('tasks.index', ['product_id' => $this->ott->id, 'due' => 'soon']))
        ->assertSee('Review player task')->assertDontSee('Overdue player task');
    $this->get(route('tasks.index', ['product_id' => $this->ott->id, 'unassigned' => 1]))
        ->assertSee('Overdue player task')->assertSee('Loose OTT task')->assertDontSee('Blocked billing task');
    $this->get(route('tasks.index', ['product_id' => $this->ott->id, 'no_module' => 1, 'state' => 'all']))
        ->assertSee('Loose OTT task')->assertDontSee('Overdue player task')
        ->assertSee('Not linked to a module');
    $this->get(route('tasks.index', ['module_id' => $this->player->id, 'state' => 'all']))
        ->assertSee('Finished player task')->assertDontSee('Blocked billing task')
        ->assertSee('Module: OTT-PLAYER');
});

it('lists every module across products with its own progress', function () {
    $this->actingAs(superAdmin())->get(route('reports.modules'))
        ->assertOk()
        ->assertSee('OTT-PLAYER')->assertSee('OTT-BILLING')
        ->assertSee(taskLink(['module_id' => $this->player->id, 'state' => 'all'], 3), false)
        ->assertSee('33%');

    $this->get(route('reports.modules', ['product_id' => $this->shr->id]))
        ->assertOk()->assertDontSee('OTT-PLAYER');
});

it('shows workload per assignee and the unassigned tasks', function () {
    $this->actingAs(superAdmin())->get(route('reports.workload'))
        ->assertOk()
        ->assertSee('Dana Developer')
        ->assertSee(taskLink(['assignee_id' => $this->dev->id, 'state' => 'all'], 2), false)
        ->assertSee('Unassigned')
        ->assertSee(taskLink(['unassigned' => 1, 'state' => 'all'], 4), false);
});

it('applies task filters to every figure and carries them into drill-down links', function () {
    $bug = optionId('task_type', 'bug');
    makeTask($this->ott->id, ['type_id' => $bug, 'module_id' => $this->billing->id, 'title' => 'Billing bug']);

    $this->actingAs(superAdmin())->get(route('reports.product', [$this->ott, 'type_id' => $bug]))
        ->assertOk()
        ->assertSee(taskLink(['type_id' => $bug, 'module_id' => $this->billing->id, 'state' => 'all'], 1), false)
        ->assertDontSee(taskLink(['type_id' => $bug, 'module_id' => $this->player->id, 'state' => 'all'], 3), false);
});

it('only reports on products and tasks within the viewer\'s access', function () {
    $this->actingAs($this->dev)->get(route('reports.index'))
        ->assertOk()
        ->assertSee('OTT')
        ->assertDontSee('SHR')
        ->assertSee(taskLink(['product_id' => $this->ott->id, 'state' => 'all'], 5), false);

    $this->get(route('reports.product', $this->shr))->assertNotFound();
    $this->get(route('reports.modules'))->assertDontSee('Secret SHR task');
    $this->get(route('reports.workload'))->assertSee(taskLink(['unassigned' => 1, 'state' => 'all'], 3), false);
});

it('adds a column when an administrator adds a task status', function () {
    $group = OptionGroup::query()->where('key', 'task_status')->firstOrFail();
    $qa = Option::query()->create(['option_group_id' => $group->id, 'value' => 'uat', 'label' => 'Client UAT', 'color' => 'info', 'behavior' => 'open', 'sort_order' => 55, 'is_active' => true]);
    makeTask($this->ott->id, ['status_id' => $qa->id]);

    $this->actingAs(superAdmin())->get(route('reports.index'))
        ->assertSee('Client UAT')
        ->assertSee(taskLink(['product_id' => $this->ott->id, 'state' => 'all', 'status_id' => $qa->id], 1), false);
});

it('paginates report rows', function () {
    config(['hub.per_page' => 2]);
    makeProduct('ABI');
    makeProduct('ERP');

    $this->actingAs(superAdmin())->get(route('reports.index'))
        ->assertSee('ABI')->assertSee('ERP')->assertDontSee('>OTT<', false)
        ->assertSee('page=2', false);
    $this->get(route('reports.index', ['page' => 2]))->assertSee('OTT');
});
