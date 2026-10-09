<?php

// Workflow 6: project inventory seeded from the reference workbook.

use App\Models\ApiReference;
use App\Models\Clarification;
use App\Models\ComponentDependency;
use App\Models\Module;
use App\Models\Product;
use App\Models\Repository;
use App\Models\Role;
use App\Rules\NoSecrets;
use App\Support\Registries;
use Database\Seeders\WorkbookSeeder;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->seed(WorkbookSeeder::class);
    $this->admin = superAdmin();
});

it('provides the initial product structure from the workbook', function () {
    expect(Product::query()->orderBy('code')->pluck('code')->all())->toBe(['ABI', 'ERP', 'OTT', 'SHR'])
        ->and(Product::query()->where('code', 'SHR')->value('name'))->toBe('AB Shared Platform')
        ->and(Repository::query()->count())->toBeGreaterThan(10);

    $this->actingAs($this->admin)->get(route('products.index'))->assertOk()
        ->assertSee('AB Shared Platform')->assertSee('ABN+ OTT')->assertSee('AB Intelligence')->assertSee('ABOS ERP');
});

it('keeps AutonOS distinct from ABOS ERP', function () {
    $abi = Product::query()->where('code', 'ABI')->firstOrFail();
    $erp = Product::query()->where('code', 'ERP')->firstOrFail();

    expect(Module::query()->where('code', 'ABI-AUTONOS')->value('product_id'))->toBe($abi->id)
        ->and(Module::query()->where('product_id', $erp->id)->where('name', 'like', '%Auton%')->exists())->toBeFalse();
});

it('lets repositories be browsed, filtered and searched', function () {
    $repo = Repository::query()->with('product')->firstOrFail();

    $this->actingAs($this->admin);
    $this->get(route('repositories.index'))->assertOk()->assertSee($repo->code);
    $this->get(route('repositories.index', ['product_id' => $repo->product_id]))->assertOk()->assertSee($repo->code);
    $this->get(route('repositories.index', ['q' => $repo->code]))->assertOk()->assertSee($repo->code);
    $this->get(route('repositories.show', $repo))->assertOk()->assertSee($repo->name);
    $this->get(route('search', ['q' => $repo->code]))->assertOk()->assertSee($repo->code);
});

it('shows repository dependencies', function () {
    $dependency = ComponentDependency::query()->where('source_type', 'repository')->firstOrFail();
    $source = Repository::query()->findOrFail($dependency->source_id);

    $this->actingAs($this->admin);
    $this->get(route('dependencies.index'))->assertOk()->assertSee($dependency->code);
    $this->get(route('repositories.show', $source))->assertOk()->assertSee($dependency->code);
});

it('opens API, database, Figma and other registry references', function () {
    $this->actingAs($this->admin);

    foreach (Registries::types() as $type) {
        $this->get(route('registries.index', $type))->assertOk();
    }

    $api = ApiReference::query()->firstOrFail();
    $this->get(route('registries.show', ['apis', $api->id]))->assertOk()->assertSee($api->code);

    $figmaRepo = Repository::query()->whereNotNull('figma_url')->first();
    if ($figmaRepo) {
        $this->get(route('repositories.show', $figmaRepo))->assertSee($figmaRepo->figma_url);
    }
});

it('flags missing information as Needs Clarification instead of inventing it', function () {
    expect(Clarification::query()->open()->count())->toBeGreaterThan(5)
        ->and(Repository::query()->whereNotNull('url')->where('url', 'not like', 'https://%')->exists())->toBeFalse();

    $this->actingAs($this->admin)->get(route('clarifications.index'))->assertOk()->assertSee('Needs Clarification');
});

it('is idempotent when re-seeded', function () {
    $repos = Repository::query()->count();
    $deps = ComponentDependency::query()->count();

    $this->seed(WorkbookSeeder::class);

    expect(Repository::query()->count())->toBe($repos)->and(ComponentDependency::query()->count())->toBe($deps);
});

it('lets a manager create and edit registry entries only inside writable products', function () {
    $ott = Product::query()->where('code', 'OTT')->firstOrFail();
    $erp = Product::query()->where('code', 'ERP')->firstOrFail();
    $manager = userWithRole(Role::PROJECT_MANAGER, [[$ott, 'write'], [$erp, 'read']]);

    $this->actingAs($manager)->post(route('registries.store', 'apis'), [
        'code' => 'OTT-SEARCH-API', 'name' => 'OTT Search API', 'product_id' => $ott->id, 'base_path' => '/api/v1/search',
    ])->assertRedirect();
    expect(ApiReference::query()->where('code', 'OTT-SEARCH-API')->exists())->toBeTrue();

    $this->post(route('registries.store', 'apis'), [
        'code' => 'ERP-X-API', 'name' => 'ERP X', 'product_id' => $erp->id,
    ])->assertForbidden();

    $erpApi = ApiReference::query()->where('product_id', $erp->id)->firstOrFail();
    $this->put(route('registries.update', ['apis', $erpApi->id]), ['code' => $erpApi->code, 'name' => 'Renamed', 'product_id' => $erp->id])->assertForbidden();
    expect($erpApi->fresh()->name)->not->toBe('Renamed');
});

it('rejects secrets in ordinary records', function (string $value) {
    expect(Validator::make(['v' => $value], ['v' => [new NoSecrets]])->fails())->toBeTrue();
})->with([
    'private key' => "-----BEGIN OPENSSH PRIVATE KEY-----\nabc",
    'aws key' => 'AKIAIOSFODNN7EXAMPLE',
    'github token' => 'ghp_'.str_repeat('a', 36),
    'credential url' => 'mysql://root:secret@10.0.0.1/db',
]);

it('accepts ordinary text in records', function () {
    expect(Validator::make(['v' => 'Uses the shared DB via the auth service; see https://example.com/docs'], ['v' => [new NoSecrets]])->fails())->toBeFalse();
});
