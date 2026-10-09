<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Module;
use App\Models\Product;
use App\Models\Repository;
use App\Rules\NoSecrets;
use App\Support\Audit;
use App\Support\Docs\DocumentConflictException;
use App\Support\Docs\DocumentPath;
use App\Support\Docs\DocumentService;
use App\Support\Docs\MarkdownRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly MarkdownRenderer $renderer,
    ) {}

    public function index(Request $request): View
    {
        $query = Document::query()->visibleTo($this->user())
            ->with(['category', 'product', 'repository', 'module', 'editor']);

        foreach (['category_id', 'product_id', 'repository_id', 'module_id'] as $filter) {
            if ($value = $request->integer($filter)) {
                $query->where($filter, $value);
            }
        }
        if (! $request->boolean('archived')) {
            $query->notArchived();
        }

        $documents = $query->orderBy('path')->get();

        // Content search reads only files of documents the user may already see.
        if ($q = mb_substr($request->string('q')->trim()->toString(), 0, 100)) {
            $documents = $documents->filter(function (Document $document) use ($q) {
                return mb_stripos($document->title, $q) !== false
                    || mb_stripos((string) $document->summary, $q) !== false
                    || mb_stripos($document->path, $q) !== false
                    || mb_stripos($this->documents->read($document), $q) !== false;
            })->values();
        }

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $documents->forPage($page, $this->perPage())->values(),
            $documents->count(),
            $this->perPage(),
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('docs.index', [
            'documents' => $paginator,
            'products' => $this->productChoices(),
            'canCreate' => Gate::allows('docs.create'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('docs.create');

        $document = new Document([
            'product_id' => $request->integer('product_id') ?: null,
            'repository_id' => $request->integer('repository_id') ?: null,
            'module_id' => $request->integer('module_id') ?: null,
            'visibility' => Document::VISIBILITY_INHERIT,
        ]);
        $this->fillProductFromPlacement($document);

        $templates = DocumentTemplate::query()->active()->ordered()->get();
        $template = $templates->firstWhere('key', $request->string('template')->toString()) ?? $templates->first();

        if ($category = $request->string('category')->toString()) {
            $document->category_id = $this->options()->idFor('document_category', $category);
        }
        if (! $document->category_id && $template?->category_id && $this->options()->find($template->category_id)?->is_active) {
            $document->category_id = $template->category_id;
        }

        return view('docs.form', $this->formData($document) + [
            'templates' => $templates->pluck('name', 'key'),
            'template' => $template?->key,
            'content' => $this->templateContent($template, $request->string('title')->toString() ?: 'New document', $document),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('docs.create');

        $data = $this->validated($request, null);
        $document = new Document($data['attributes']);
        Gate::authorize('create-in', ['docs.create', $this->scopeFor($document)]);

        $category = $this->options()->find($document->category_id);
        $base = DocumentPath::build($document->product?->slug, $category?->value, $data['attributes']['title'], $request->string('slug')->toString() ?: null);
        $path = $base;
        for ($i = 2; Document::query()->where('path', $path)->exists() || $this->documents->storage()->exists($path); $i++) {
            $path = DocumentPath::normalize(substr($base, 0, -3).'-'.$i.'.md');
        }

        try {
            $document = $this->documents->create(
                [...$data['attributes'], 'path' => $path],
                $data['content'],
                $this->user(),
                $data['summary'],
            );
        } catch (DocumentConflictException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('docs.show', $document)->with('status', 'Document created at '.$document->path.'.');
    }

    public function show(Document $document): View
    {
        $this->authorizeView($document);
        $document->load(['category', 'product', 'repository', 'module', 'owner', 'creator', 'editor']);

        $exists = $this->documents->fileExists($document);

        return view('docs.show', [
            'document' => $document,
            'html' => $exists ? $this->renderer->render($this->documents->read($document), $document->path) : null,
            'fileMissing' => ! $exists,
            'changedOutside' => $this->documents->changedOutsideHub($document),
            'repoLinks' => $this->documents->repositoryLinks($document),
            'tasks' => $this->paginateList($document->tasks()->visibleTo($this->user())->with(['status', 'priority'])->orderBy('tasks.product_id')->orderBy('tasks.number'), 'tasks'),
            'revisionCount' => $document->revisions()->count(),
        ]);
    }

    public function edit(Document $document): View
    {
        $this->authorizeView($document);
        Gate::authorize('update', $document);

        return view('docs.form', $this->formData($document) + [
            'content' => $this->documents->read($document),
            'baseHash' => $this->documents->currentHash($document),
            'changedOutside' => $this->documents->changedOutsideHub($document),
        ]);
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $this->authorizeView($document);
        Gate::authorize('update', $document);

        $data = $this->validated($request, $document);
        $original = $document->only(['product_id', 'repository_id', 'module_id']);

        $probe = (clone $document)->fill($data['attributes']);
        if ($probe->only(['product_id', 'repository_id', 'module_id']) != $original) {
            Gate::authorize('create-in', ['docs.create', $this->scopeFor($probe)]);
        }

        try {
            $this->documents->update($document, $data['attributes'], $data['content'], $this->user(), $data['summary'], $request->input('base_hash'));
        } catch (DocumentConflictException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('docs.show', $document)->with('status', 'Document saved.');
    }

    public function history(Document $document): View
    {
        $this->authorizeView($document);

        return view('docs.history', [
            'document' => $document,
            'revisions' => $document->revisions()->with('user')->paginate($this->perPage()),
            'repoLinks' => $this->documents->repositoryLinks($document),
        ]);
    }

    public function archive(Document $document): RedirectResponse
    {
        $this->authorizeView($document);
        Gate::authorize('archive', $document);

        $document->forceFill(['archived_at' => now(), 'updated_by' => $this->user()->id])->save();
        Audit::record('document.archived', $document);

        return redirect()->route('docs.show', $document)->with('status', 'Document archived. The file is kept on disk.');
    }

    public function restore(Document $document): RedirectResponse
    {
        $this->authorizeView($document);
        Gate::authorize('archive', $document);

        $document->forceFill(['archived_at' => null, 'updated_by' => $this->user()->id])->save();
        Audit::record('document.restored', $document);

        return redirect()->route('docs.show', $document)->with('status', 'Document restored.');
    }

    public function preview(Request $request): JsonResponse
    {
        abort_unless(Gate::allows('docs.create') || Gate::allows('docs.update'), 403);

        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:'.config('hub.docs.max_bytes')],
            'path' => ['nullable', 'string', 'max:255'],
        ]);
        $path = $validated['path'] ?? '';
        $path = DocumentPath::isValid($path) ? $path : 'preview.md';

        return response()->json(['html' => $this->renderer->render($validated['content'] ?? '', $path)]);
    }

    /**
     * Follows an internal Markdown link. Missing and unreadable documents both return 404.
     */
    public function resolve(Request $request): RedirectResponse
    {
        $path = $request->string('path')->toString();
        $anchor = '';
        if (str_contains($path, '#')) {
            [$path, $fragment] = explode('#', $path, 2);
            $anchor = '#'.preg_replace('/[^A-Za-z0-9_-]/', '', $fragment);
        }

        abort_unless(DocumentPath::isValid($path), 404);
        $document = Document::query()->where('path', $path)->first();
        abort_if($document === null || ! $this->access()->canRead($this->user(), $document), 404);

        return redirect()->to(route('docs.show', $document).$anchor);
    }

    /**
     * @return array{attributes: array<string, mixed>, content: string, summary: ?string}
     */
    private function validated(Request $request, ?Document $document): array
    {
        $user = $this->user();
        $readable = fn (string $class) => function (string $attribute, mixed $value, \Closure $fail) use ($class, $user) {
            if (! $this->access()->canRead($user, (is_numeric($value) ? $class::query()->find((int) $value) : null))) {
                $fail('The selected :attribute is invalid.');
            }
        };

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200', new NoSecrets],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'category_id' => ['required', $this->options()->rule('document_category', $document?->category_id)],
            'product_id' => ['nullable', 'integer', $readable(Product::class)],
            'repository_id' => ['nullable', 'integer', $readable(Repository::class)],
            'module_id' => ['nullable', 'integer', $readable(Module::class)],
            'visibility' => ['required', Rule::in(array_keys(Document::VISIBILITIES))],
            'summary' => ['nullable', 'string', 'max:500', new NoSecrets],
            'content' => ['required', 'string', 'max:'.config('hub.docs.max_bytes'), new NoSecrets],
            'change_summary' => ['nullable', 'string', 'max:255', new NoSecrets],
            'base_hash' => ['nullable', 'string', 'size:64'],
        ]);

        $attributes = [
            'title' => $validated['title'],
            'category_id' => (int) $validated['category_id'],
            'product_id' => isset($validated['product_id']) ? (int) $validated['product_id'] : null,
            'repository_id' => isset($validated['repository_id']) ? (int) $validated['repository_id'] : null,
            'module_id' => isset($validated['module_id']) ? (int) $validated['module_id'] : null,
            'visibility' => $validated['visibility'],
            'summary' => $validated['summary'] ?? null,
        ];

        $probe = new Document($attributes);
        $this->fillProductFromPlacement($probe);
        $attributes['product_id'] = $probe->product_id;

        $repository = $probe->repository_id ? Repository::query()->find($probe->repository_id) : null;
        $module = $probe->module_id ? Module::query()->find($probe->module_id) : null;
        if ($repository && $repository->product_id !== $probe->product_id) {
            throw ValidationException::withMessages(['repository_id' => 'The repository belongs to a different product.']);
        }
        if ($module && $module->product_id !== $probe->product_id) {
            throw ValidationException::withMessages(['module_id' => 'The module belongs to a different product.']);
        }

        // Widening visibility to every user is an access decision, not an editing one.
        $currentVisibility = $document !== null ? $document->visibility : Document::VISIBILITY_INHERIT;
        if ($attributes['visibility'] !== $currentVisibility && $attributes['visibility'] === Document::VISIBILITY_ALL_USERS && ! Gate::allows('access.manage')) {
            throw ValidationException::withMessages(['visibility' => 'Only access administrators can make a document visible to all users.']);
        }

        return [
            'attributes' => $attributes,
            'content' => $validated['content'],
            'summary' => $validated['change_summary'] ?? null,
        ];
    }

    private function fillProductFromPlacement(Document $document): void
    {
        if ($document->product_id) {
            return;
        }
        if ($document->module_id) {
            $document->product_id = Module::query()->whereKey($document->module_id)->value('product_id');
        } elseif ($document->repository_id) {
            $document->product_id = Repository::query()->whereKey($document->repository_id)->value('product_id');
        }
    }

    /**
     * The most specific placement determines who may create documents there.
     */
    private function scopeFor(Document $document): ?Model
    {
        return match (true) {
            (bool) $document->module_id => Module::query()->find($document->module_id),
            (bool) $document->repository_id => Repository::query()->find($document->repository_id),
            (bool) $document->product_id => Product::query()->find($document->product_id),
            default => null,
        };
    }

    private function templateContent(?DocumentTemplate $template, string $title, Document $document): string
    {
        $content = $template !== null ? $template->content : "# {{title}}\n";
        $product = $document->product_id ? Product::query()->find($document->product_id) : null;

        return strtr($content, [
            '{{title}}' => $title,
            '{{product}}' => $product ? $product->code.' — '.$product->name : 'Cross-product',
            '{{date}}' => now()->toDateString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Document $document): array
    {
        $user = $this->user();

        return [
            'document' => $document,
            'products' => $this->productChoices(),
            'repositories' => Repository::query()->visibleTo($user)->orderBy('code')->get()
                ->mapWithKeys(fn ($r) => [$r->id => $r->code.' — '.$r->name]),
            'modules' => Module::query()->visibleTo($user)->orderBy('code')->get()
                ->mapWithKeys(fn ($m) => [$m->id => $m->code.' — '.$m->name]),
            'canSetAllUsers' => Gate::allows('access.manage'),
        ];
    }
}
