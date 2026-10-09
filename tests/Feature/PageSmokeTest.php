<?php

// Every screen renders without errors for an administrator and a restricted user.

use App\Models\Application;
use App\Models\Clarification;
use App\Models\ComponentDependency;
use App\Models\Module;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\Repository;
use App\Models\Role;
use App\Models\Task;
use App\Support\Registries;
use Database\Seeders\WorkbookSeeder;

beforeEach(function () {
    $this->seed(WorkbookSeeder::class);
    $this->ott = Product::query()->where('code', 'OTT')->firstOrFail();
    $this->doc = makeDocument('products/ott/overview.md', 'OTT Overview', "# OTT\n\nSee [glossary](../../glossary.md).\n", ['product_id' => $this->ott->id]);
    $this->task = Task::query()->create([
        'key' => 'OTT-1', 'number' => 1, 'title' => 'Smoke task', 'product_id' => $this->ott->id,
        'type_id' => optionId('task_type', 'feature'), 'status_id' => optionId('task_status', 'blocked'),
        'priority_id' => optionId('priority', 'medium'), 'blocker_description' => 'Waiting',
    ]);
    $this->task->documents()->attach($this->doc->id);
});

function smokeUrls(Product $ott, $doc, $task): array
{
    $repo = Repository::query()->where('product_id', $ott->id)->firstOrFail();
    $module = Module::query()->where('product_id', $ott->id)->first();
    $application = Application::query()->where('product_id', $ott->id)->first();
    $dependency = ComponentDependency::query()->first();

    $urls = [
        route('dashboard'), route('search', ['q' => 'ott']), route('profile.edit'),
        route('products.index'), route('products.show', $ott), route('products.edit', $ott), route('products.create'),
        route('applications.index'), route('applications.create'),
        route('repositories.index'), route('repositories.show', $repo), route('repositories.edit', $repo), route('repositories.create'),
        route('modules.index'), route('modules.create'),
        route('dependencies.index'), route('dependencies.create'),
        route('clarifications.index'),
        route('docs.index'), route('docs.create', ['product_id' => $ott->id, 'template' => 'architecture']), route('docs.show', $doc), route('docs.edit', $doc), route('docs.history', $doc),
        route('tasks.index'), route('tasks.create'), route('tasks.show', $task), route('tasks.edit', $task),
    ];
    if ($module) {
        array_push($urls, route('modules.show', $module), route('modules.edit', $module));
    }
    if ($application) {
        array_push($urls, route('applications.show', $application), route('applications.edit', $application));
    }
    if ($dependency) {
        $urls[] = route('dependencies.edit', $dependency);
    }
    foreach (Registries::all() as $type => $definition) {
        $urls[] = route('registries.index', $type);
        $urls[] = route('registries.create', $type);
        $record = $definition['model']::query()->first();
        if ($record) {
            $urls[] = route('registries.show', [$type, $record->getKey()]);
            $urls[] = route('registries.edit', [$type, $record->getKey()]);
        }
    }

    return $urls;
}

it('renders every screen for a super admin', function () {
    $admin = superAdmin();
    $this->actingAs($admin);

    $urls = [
        ...smokeUrls($this->ott, $this->doc, $this->task),
        route('admin.users.index'), route('admin.users.create'), route('admin.users.edit', $admin), route('admin.users.access', $admin),
        route('admin.users.access', userWithRole(Role::VIEWER, [[$this->ott, 'read']])),
        route('admin.roles.index'), route('admin.options.index'), route('admin.activity.index'),
        route('admin.options.show', OptionGroup::query()->where('key', 'task_status')->firstOrFail()),
    ];

    foreach ($urls as $url) {
        $response = $this->get($url);
        expect($response->status())->toBe(200, "GET {$url} returned {$response->status()}");
    }
});

it('renders readable screens for a restricted viewer and never errors', function () {
    $viewer = userWithRole(Role::VIEWER, [[$this->ott, 'read']]);
    $this->actingAs($viewer);

    foreach (smokeUrls($this->ott, $this->doc, $this->task) as $url) {
        $status = $this->get($url)->status();
        expect($status)->toBeIn([200, 403, 404], "GET {$url} returned {$status}");
    }

    // Read pages are reachable; write pages are not.
    $this->get(route('products.show', $this->ott))->assertOk();
    $this->get(route('docs.show', $this->doc))->assertOk();
    $this->get(route('tasks.show', $this->task))->assertOk();
    $this->get(route('products.edit', $this->ott))->assertForbidden();
    $this->get(route('docs.edit', $this->doc))->assertForbidden();
});

it('shows clarifications only for readable scopes', function () {
    $viewer = userWithRole(Role::VIEWER, [[$this->ott, 'read']]);
    $erp = Product::query()->where('code', 'ERP')->firstOrFail();
    $erpRepoCodes = Repository::query()->where('product_id', $erp->id)
        ->whereIn('id', Clarification::query()->where('subject_type', 'repository')->select('subject_id'))
        ->pluck('code');
    expect($erpRepoCodes)->not->toBeEmpty();

    $response = $this->actingAs($viewer)->get(route('clarifications.index', ['status' => 'all']));
    $response->assertOk();
    foreach ($erpRepoCodes as $code) {
        $response->assertDontSee('>'.$code.'<', false);
    }
    $this->actingAs(superAdmin())->get(route('clarifications.index', ['status' => 'all', 'product_id' => $erp->id]))
        ->assertSee('>'.$erpRepoCodes->first().'<', false);
});
