<?php

use App\Models\AccessRule;
use App\Models\Document;
use App\Models\Module;
use App\Models\Product;
use App\Models\Repository;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Support\Docs\DocumentService;
use App\Support\Options\Options;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Create a user with the given role and optional scoped access rules.
 *
 * @param  list<array{0: Model|null, 1: string}>  $rules  [[$scopeModel|null(global), 'read'|'write'|'deny'], ...]
 */
function userWithRole(string $roleKey, array $rules = [], array $attributes = []): User
{
    $user = User::factory()->create([
        'role_id' => Role::query()->where('key', $roleKey)->value('id'),
        'is_active' => true,
        ...$attributes,
    ]);

    foreach ($rules as [$scope, $permission]) {
        grantAccess($user, $scope, $permission);
    }

    return $user;
}

function grantAccess(User $user, ?Model $scope, string $permission): AccessRule
{
    return AccessRule::query()->create([
        'user_id' => $user->id,
        'scope_type' => $scope?->getMorphClass() ?? 'global',
        'scope_id' => $scope?->getKey(),
        'permission' => $permission,
    ]);
}

function superAdmin(): User
{
    return userWithRole(Role::SUPER_ADMIN);
}

function makeProduct(string $code, array $attributes = []): Product
{
    return Product::query()->create([
        'code' => $code,
        'name' => $attributes['name'] ?? "Product {$code}",
        'slug' => $attributes['slug'] ?? Str::slug($code.'-product'),
        ...$attributes,
    ]);
}

function makeRepository(Product $product, string $code, array $attributes = []): Repository
{
    return Repository::query()->create([
        'code' => $code,
        'name' => $attributes['name'] ?? strtolower($code).'-service',
        'product_id' => $product->id,
        ...$attributes,
    ]);
}

function makeModule(Product $product, string $code, array $attributes = []): Module
{
    return Module::query()->create([
        'code' => $code,
        'name' => $attributes['name'] ?? "Module {$code}",
        'product_id' => $product->id,
        ...$attributes,
    ]);
}

function makeTask(int $productId, array $attributes = []): Task
{
    $number = Task::query()->count() + 1;

    return Task::query()->create([
        'key' => 'T-'.$number, 'number' => $number,
        'title' => 'Existing task', 'product_id' => $productId,
        'type_id' => optionId('task_type', 'feature'), 'status_id' => optionId('task_status', 'todo'),
        'priority_id' => optionId('priority', 'low'), ...$attributes,
    ]);
}

function optionId(string $group, string $value): int
{
    $id = app(Options::class)->idFor($group, $value);
    if ($id === null) {
        throw new RuntimeException("Option {$group}.{$value} not seeded.");
    }

    return $id;
}

/**
 * Create a registered Markdown document (real file + metadata row).
 */
function makeDocument(string $path, string $title, string $content, array $attributes = []): Document
{
    return app(DocumentService::class)->create([
        'path' => $path,
        'title' => $title,
        'category_id' => optionId('document_category', 'overview'),
        'visibility' => Document::VISIBILITY_INHERIT,
        ...$attributes,
    ], $content, User::query()->first() ?? superAdmin());
}

function docsRoot(): string
{
    return base_path((string) config('hub.docs.path'));
}
