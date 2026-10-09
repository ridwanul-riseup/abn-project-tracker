<?php

// Workflow 3: Markdown documentation.

use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\Role;
use App\Support\Docs\DocumentPath;
use App\Support\Docs\DocumentStorage;
use App\Support\Docs\InvalidDocumentPathException;
use App\Support\Docs\MarkdownRenderer;
use Database\Seeders\DocumentSeeder;
use Database\Seeders\WorkbookSeeder;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->ott = makeProduct('OTT', ['name' => 'ABN+ OTT', 'slug' => 'ott']);
    $this->erp = makeProduct('ERP', ['name' => 'ABOS ERP', 'slug' => 'abos-erp']);
    $this->editor = userWithRole(Role::DEVELOPER, [[$this->ott, 'write']]);
    $this->category = optionId('document_category', 'architecture');
});

function docPayload(array $overrides = []): array
{
    return [
        'title' => 'Streaming Architecture',
        'category_id' => optionId('document_category', 'architecture'),
        'visibility' => 'inherit',
        'content' => "# Streaming\n\nSome **bold** text.\n",
        'change_summary' => 'Initial draft',
        ...$overrides,
    ];
}

it('lets an authorized user browse documentation', function () {
    makeDocument('products/ott/overview.md', 'OTT Overview', "# OTT\n", ['product_id' => $this->ott->id]);

    $this->actingAs($this->editor)->get(route('docs.index'))->assertOk()->assertSee('OTT Overview');
});

it('lets an editor create a Markdown document stored as a real .md file', function () {
    $this->actingAs($this->editor)
        ->post(route('docs.store'), docPayload(['product_id' => $this->ott->id]))
        ->assertRedirect();

    $doc = Document::query()->where('title', 'Streaming Architecture')->firstOrFail();
    expect($doc->path)->toBe('products/ott/streaming-architecture.md')
        ->and($doc->created_by)->toBe($this->editor->id)
        ->and(file_get_contents(docsRoot().'/'.$doc->path))->toBe("# Streaming\n\nSome **bold** text.\n")
        ->and(DocumentRevision::query()->where('document_id', $doc->id)->value('action'))->toBe('created');
});

it('does not let an editor create documents in products they cannot write', function () {
    grantAccess($this->editor, $this->erp, 'read');

    $this->actingAs($this->editor)
        ->post(route('docs.store'), docPayload(['product_id' => $this->erp->id]))
        ->assertForbidden();

    $this->post(route('docs.store'), docPayload())->assertForbidden(); // cross-product needs global write
    expect(Document::query()->count())->toBe(0);
});

it('never accepts a user-supplied path', function () {
    $this->actingAs($this->editor)->post(route('docs.store'), docPayload([
        'product_id' => $this->ott->id,
        'path' => '../../.env',
        'slug' => '../../.env',
    ]))->assertSessionHasErrors('slug');

    $this->post(route('docs.store'), docPayload(['product_id' => $this->ott->id, 'path' => '../../.env']))->assertRedirect();
    expect(Document::query()->value('path'))->toBe('products/ott/streaming-architecture.md');
});

it('lets an editor update a document and records a revision', function () {
    $doc = makeDocument('products/ott/overview.md', 'OTT Overview', "# OTT\n", ['product_id' => $this->ott->id, 'category_id' => $this->category]);
    $hash = hash('sha256', "# OTT\n");

    $this->actingAs($this->editor)->get(route('docs.edit', $doc))->assertOk()->assertSee($hash);
    $this->put(route('docs.update', $doc), docPayload([
        'title' => 'OTT Overview', 'product_id' => $this->ott->id,
        'content' => "# OTT\n\nUpdated.\n", 'base_hash' => $hash, 'change_summary' => 'Add detail',
    ]))->assertRedirect(route('docs.show', $doc));

    expect(file_get_contents(docsRoot().'/products/ott/overview.md'))->toBe("# OTT\n\nUpdated.\n")
        ->and($doc->fresh()->updated_by)->toBe($this->editor->id)
        ->and($doc->revisions()->first()->summary)->toBe('Add detail');
});

it('detects concurrent edits instead of overwriting them', function () {
    $doc = makeDocument('products/ott/overview.md', 'OTT Overview', "# OTT\n", ['product_id' => $this->ott->id, 'category_id' => $this->category]);
    $staleHash = hash('sha256', "# OTT\n");
    file_put_contents(docsRoot().'/products/ott/overview.md', "# OTT\n\nChanged in Git.\n");

    $this->actingAs($this->editor)->put(route('docs.update', $doc), docPayload([
        'title' => 'OTT Overview', 'product_id' => $this->ott->id, 'content' => "# Mine\n", 'base_hash' => $staleHash,
    ]))->assertSessionHas('error');

    expect(file_get_contents(docsRoot().'/products/ott/overview.md'))->toBe("# OTT\n\nChanged in Git.\n");
});

it('renders Markdown correctly', function () {
    $doc = makeDocument('products/ott/overview.md', 'OTT Overview', "# Title\n\n- item one\n\n| A | B |\n|---|---|\n| 1 | 2 |\n\n```php\necho 1;\n```\n", ['product_id' => $this->ott->id]);

    $this->actingAs($this->editor)->get(route('docs.show', $doc))->assertOk()
        ->assertSee('<li>item one</li>', false)
        ->assertSee('<table>', false)
        ->assertSee('<code class="language-php">', false);
});

it('does not execute unsafe HTML or JavaScript', function () {
    $html = app(MarkdownRenderer::class)->render(
        "<script>alert(1)</script>\n\n<img src=x onerror=alert(2)>\n\n[click](javascript:alert(3))\n\n<iframe src=\"https://evil.test\"></iframe>\n",
        'products/ott/x.md',
    );

    expect($html)->not->toContain('<script')
        ->not->toContain('<img src=x')
        ->not->toContain('<iframe')
        ->not->toContain('href="javascript:')
        ->toContain('&lt;script&gt;');
});

it('rewrites relative links to internal document routes', function () {
    $html = app(MarkdownRenderer::class)->render('[Glossary](../../glossary.md#terms)', 'products/ott/overview.md');

    expect($html)->toContain('href="'.route('docs.resolve', ['path' => 'glossary.md']).'#terms"');
});

it('blocks unauthorized users from reading or editing restricted documents', function () {
    $doc = makeDocument('products/abos-erp/finance.md', 'ERP Finance', "# Finance\n", ['product_id' => $this->erp->id]);

    $this->actingAs($this->editor);
    $this->get(route('docs.show', $doc))->assertNotFound();
    $this->get(route('docs.edit', $doc))->assertNotFound();
    $this->put(route('docs.update', $doc), docPayload(['product_id' => $this->ott->id]))->assertNotFound();
    $this->post(route('docs.archive', $doc))->assertNotFound();
    $this->get(route('docs.index'))->assertDontSee('ERP Finance');

    expect(file_get_contents(docsRoot().'/products/abos-erp/finance.md'))->toBe("# Finance\n");
});

it('only lets access administrators make a document visible to everyone', function () {
    $this->actingAs($this->editor)
        ->post(route('docs.store'), docPayload(['product_id' => $this->ott->id, 'visibility' => 'all_users']))
        ->assertSessionHasErrors('visibility');
});

it('rejects secrets in document content', function () {
    $this->actingAs($this->editor)->post(route('docs.store'), docPayload([
        'product_id' => $this->ott->id,
        'content' => "# Config\n\n-----BEGIN RSA PRIVATE KEY-----\nMIIEow\n-----END RSA PRIVATE KEY-----\n",
    ]))->assertSessionHasErrors('content');

    $this->post(route('docs.store'), docPayload([
        'product_id' => $this->ott->id,
        'content' => "DB: https://admin:hunter2@db.internal.test/app\n",
    ]))->assertSessionHasErrors('content');

    expect(Document::query()->count())->toBe(0);
});

it('archives without deleting the file', function () {
    $doc = makeDocument('products/ott/old.md', 'Old Doc', "# Old\n", ['product_id' => $this->ott->id]);
    $manager = userWithRole(Role::PROJECT_MANAGER, [[$this->ott, 'write']]);

    $this->actingAs($manager)->post(route('docs.archive', $doc))->assertRedirect();

    expect($doc->fresh()->archived_at)->not->toBeNull()
        ->and(is_file(docsRoot().'/products/ott/old.md'))->toBeTrue();
});

it('validates document paths strictly', function (string $path, bool $valid) {
    expect(DocumentPath::isValid($path))->toBe($valid);
})->with([
    ['products/ott/overview.md', true],
    ['README.md', true],
    ['../secrets.md', false],
    ['products/../../.env', false],
    ['/etc/passwd.md', false],
    ['products/.hidden.md', false],
    ['products/ott/overview.php', false],
    ['products\\ott\\x.md', false],
    ['products/ott/over view.md', false],
]);

it('refuses to read or write outside the docs root', function () {
    $storage = app(DocumentStorage::class);

    expect(fn () => $storage->read('../composer.json'))->toThrow(InvalidDocumentPathException::class)
        ->and(fn () => $storage->write('../../app/evil.md', 'x'))->toThrow(InvalidDocumentPathException::class);
});

it('refuses to follow symlinks out of the docs root', function () {
    @symlink(base_path('composer.json'), docsRoot().'/link.md');
    $storage = app(DocumentStorage::class);

    expect(fn () => $storage->read('link.md'))->toThrow(InvalidDocumentPathException::class);
})->skip(fn () => ! function_exists('symlink'), 'symlinks unavailable');

it('ships and registers every seeded document', function () {
    foreach (DocumentSeeder::documents() as [$path]) {
        $source = base_path('docs/'.$path);
        expect(DocumentPath::isValid($path))->toBeTrue()
            ->and(is_file($source))->toBeTrue("Missing shipped document docs/{$path}");

        File::ensureDirectoryExists(dirname(docsRoot().'/'.$path));
        File::copy($source, docsRoot().'/'.$path);
    }

    $this->seed([WorkbookSeeder::class, DocumentSeeder::class]);

    expect(Document::query()->count())->toBe(count(DocumentSeeder::documents()))
        ->and(Document::query()->where('path', 'products/ab-intelligence/auton-os.md')->first()->module->code)->toBe('ABI-AUTONOS')
        ->and(DocumentRevision::query()->where('action', 'registered')->count())->toBe(count(DocumentSeeder::documents()));
});

it('renders a sanitized live preview for editors only', function () {
    $this->actingAs($this->editor)
        ->postJson(route('docs.preview'), ['content' => '**hi** <script>x</script>'])
        ->assertOk()
        ->assertJsonPath('html', fn (string $html) => str_contains($html, '<strong>hi</strong>') && ! str_contains($html, '<script>'));

    $this->actingAs(userWithRole(Role::VIEWER))->postJson(route('docs.preview'), ['content' => 'x'])->assertForbidden();
});
