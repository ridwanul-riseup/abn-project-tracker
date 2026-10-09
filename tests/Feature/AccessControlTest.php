<?php

// Workflow 2: access control.

use App\Models\Document;
use App\Models\Role;
use App\Models\Task;
use App\Support\Access\AccessService;

beforeEach(function () {
    $this->ott = makeProduct('OTT', ['name' => 'ABN+ OTT']);
    $this->erp = makeProduct('ERP', ['name' => 'ABOS ERP']);
    $this->ottRepo = makeRepository($this->ott, 'OTT-API');
    $this->erpRepo = makeRepository($this->erp, 'ERP-FINANCE', ['sensitive_notes' => 'internal-only note']);
    $this->viewer = userWithRole(Role::VIEWER, [[$this->ott, 'read']]);
});

it('shows authorized products and hides restricted ones', function () {
    $this->actingAs($this->viewer);

    $this->get(route('products.index'))->assertOk()->assertSee('ABN+ OTT')->assertDontSee('ABOS ERP');
    $this->get(route('products.show', $this->ott))->assertOk();
    $this->get(route('repositories.index'))->assertSee('OTT-API')->assertDontSee('ERP-FINANCE');
});

it('returns 404 when a record ID of a restricted resource is requested directly', function () {
    $doc = makeDocument('products/abos-erp/secret-plan.md', 'ERP Secret Plan', "# Plan\n", ['product_id' => $this->erp->id]);
    $task = Task::query()->create([
        'key' => 'ERP-1', 'number' => 1, 'title' => 'ERP hidden task', 'product_id' => $this->erp->id,
        'type_id' => optionId('task_type', 'feature'), 'status_id' => optionId('task_status', 'todo'),
        'priority_id' => optionId('priority', 'medium'),
    ]);

    $this->actingAs($this->viewer);
    $this->get(route('products.show', $this->erp))->assertNotFound();
    $this->get(route('products.edit', $this->erp))->assertNotFound();
    $this->get(route('repositories.show', $this->erpRepo))->assertNotFound();
    $this->get(route('docs.show', $doc))->assertNotFound();
    $this->get(route('docs.history', $doc))->assertNotFound();
    $this->get(route('docs.resolve', ['path' => $doc->path]))->assertNotFound();
    $this->get(route('tasks.show', $task))->assertNotFound();
    $this->put(route('repositories.update', $this->erpRepo), ['code' => 'X', 'name' => 'x', 'product_id' => $this->erp->id])->assertNotFound();
    $this->post(route('tasks.comments.store', $task), ['body' => 'hi'])->assertNotFound();
    expect($this->erpRepo->fresh()->code)->toBe('ERP-FINANCE');
});

it('blocks moving a record into a product the user cannot write', function () {
    $manager = userWithRole(Role::PROJECT_MANAGER, [[$this->ott, 'write'], [$this->erp, 'read']]);

    $this->actingAs($manager)->put(route('repositories.update', $this->ottRepo), [
        'code' => 'OTT-API', 'name' => 'ott-api', 'product_id' => $this->erp->id,
    ])->assertForbidden();

    expect($this->ottRepo->fresh()->product_id)->toBe($this->ott->id);
});

it('lets an explicit deny override inherited product access', function () {
    grantAccess($this->viewer, $this->ottRepo, 'deny');
    app(AccessService::class)->flush();

    $this->actingAs($this->viewer);
    $this->get(route('products.show', $this->ott))->assertOk();
    $this->get(route('repositories.show', $this->ottRepo))->assertNotFound();
});

it('does not let users edit documents without write permission', function () {
    $doc = makeDocument('products/ott/overview.md', 'OTT Overview', "# OTT\n", ['product_id' => $this->ott->id]);
    $developer = userWithRole(Role::DEVELOPER, [[$this->ott, 'read']]);

    $this->actingAs($developer);
    $this->get(route('docs.show', $doc))->assertOk();
    $this->get(route('docs.edit', $doc))->assertForbidden();
    $this->put(route('docs.update', $doc), [
        'title' => 'Hacked', 'category_id' => $doc->category_id, 'visibility' => 'inherit', 'content' => "# Hacked\n",
    ])->assertForbidden();

    expect(file_get_contents(docsRoot().'/products/ott/overview.md'))->toBe("# OTT\n")
        ->and($doc->fresh()->title)->toBe('OTT Overview');
});

it('does not let a viewer create or modify tasks', function () {
    $task = Task::query()->create([
        'key' => 'OTT-1', 'number' => 1, 'title' => 'Player bug', 'product_id' => $this->ott->id,
        'type_id' => optionId('task_type', 'bug'), 'status_id' => optionId('task_status', 'todo'),
        'priority_id' => optionId('priority', 'high'), 'assignee_id' => $this->viewer->id,
    ]);

    $this->actingAs($this->viewer);
    $this->get(route('tasks.create'))->assertForbidden();
    $this->post(route('tasks.store'), [
        'title' => 'New', 'product_id' => $this->ott->id, 'type_id' => optionId('task_type', 'bug'), 'priority_id' => optionId('priority', 'low'),
    ])->assertForbidden();
    $this->get(route('tasks.edit', $task))->assertForbidden();
    $this->patch(route('tasks.progress', $task), ['status_id' => optionId('task_status', 'done')])->assertForbidden();
    $this->post(route('tasks.comments.store', $task), ['body' => 'Viewer note'])->assertForbidden();

    expect(Task::query()->count())->toBe(1)->and($task->fresh()->status_id)->toBe(optionId('task_status', 'todo'));
});

it('does not expose restricted records in search results', function () {
    makeDocument('products/abos-erp/payroll.md', 'Payroll Integration', "# Payroll\nUses the zebra-ledger engine.\n", ['product_id' => $this->erp->id]);
    makeDocument('products/ott/player.md', 'OTT Player', "# Player\nAlso mentions zebra-ledger once.\n", ['product_id' => $this->ott->id]);

    $this->actingAs($this->viewer);

    $this->get(route('search', ['q' => 'zebra-ledger']))->assertOk()
        ->assertSee('OTT Player')
        ->assertDontSee('Payroll Integration')
        ->assertDontSee('payroll.md');

    $this->get(route('search', ['q' => 'ERP']))->assertOk()->assertDontSee('ERP-FINANCE')->assertDontSee('ABOS ERP');
    $this->get(route('docs.index', ['q' => 'zebra']))->assertSee('OTT Player')->assertDontSee('Payroll Integration');
});

it('hides sensitive repository notes from users without the capability', function () {
    grantAccess($this->viewer, $this->erp, 'read');
    app(AccessService::class)->flush();

    $this->actingAs($this->viewer)->get(route('repositories.show', $this->erpRepo))->assertOk()->assertDontSee('internal-only note');
    $this->actingAs(superAdmin())->get(route('repositories.show', $this->erpRepo))->assertOk()->assertSee('internal-only note');
});

it('applies permission changes immediately', function () {
    $this->actingAs($this->viewer);
    $this->get(route('products.show', $this->erp))->assertNotFound();

    $rule = grantAccess($this->viewer, $this->erp, 'read');
    $this->get(route('products.show', $this->erp))->assertOk();

    $rule->forceFill(['revoked_at' => now()])->save();
    $this->get(route('products.show', $this->erp))->assertNotFound();
});

it('shows documents visible to all users but honours denies on them', function () {
    $doc = makeDocument('glossary.md', 'Glossary', "# Glossary\n", ['visibility' => Document::VISIBILITY_ALL_USERS]);
    $outsider = userWithRole(Role::VIEWER);

    $this->actingAs($outsider)->get(route('docs.show', $doc))->assertOk();

    grantAccess($outsider, $doc, 'deny');
    $this->get(route('docs.show', $doc))->assertNotFound();
});

it('does not leak restricted record names in the activity feed', function () {
    $this->actingAs(superAdmin());
    $this->put(route('products.update', $this->erp), ['code' => 'ERP', 'name' => 'Top Secret ERP Rename', 'slug' => $this->erp->slug])->assertRedirect();

    $this->actingAs($this->viewer)->get('/')->assertOk()->assertDontSee('Top Secret ERP Rename');
});

it('sends security headers and disables caching for authenticated pages', function () {
    $this->actingAs($this->viewer)->get('/')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy');

    expect($this->get('/')->headers->get('Cache-Control'))->toContain('no-store');
});

it('requires authentication everywhere', function () {
    $this->get('/')->assertRedirect(route('login'));
    $this->get(route('docs.index'))->assertRedirect(route('login'));
    $this->get(route('search', ['q' => 'ott']))->assertRedirect(route('login'));
});
