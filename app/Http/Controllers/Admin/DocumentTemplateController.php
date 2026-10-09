<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Rules\NoSecrets;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Global settings: starting content offered when a new document is created.
 * Keys are immutable; templates are not referenced by documents, so they can
 * be deleted. Without any active template the editor starts with the title only.
 */
class DocumentTemplateController extends Controller
{
    private const MAX_CONTENT = 60000;

    public function index(): View
    {
        Gate::authorize('settings.manage');

        return view('admin.templates.index', [
            'templates' => DocumentTemplate::query()->with('category')->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('settings.manage');

        return view('admin.templates.form', [
            'template' => new DocumentTemplate(['is_active' => true, 'content' => "# {{title}}\n\n"]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $request->merge(['key' => Str::of($request->input('key') ?: $request->input('name', ''))->lower()->slug('_')->toString()]);
        $validated = $request->validate($this->rules(null) + [
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9_]*$/', Rule::unique('document_templates', 'key')],
        ]);

        $template = DocumentTemplate::query()->create([
            ...$this->attributes($validated),
            'key' => $validated['key'],
            'sort_order' => (int) DocumentTemplate::query()->max('sort_order') + 1,
            'is_active' => true,
        ]);

        return redirect()->route('admin.templates.index')->with('status', "Template “{$template->name}” created.");
    }

    public function edit(DocumentTemplate $template): View
    {
        Gate::authorize('settings.manage');

        return view('admin.templates.form', ['template' => $template]);
    }

    public function update(Request $request, DocumentTemplate $template): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $template->update($this->attributes($request->validate($this->rules($template))));

        return redirect()->route('admin.templates.index')->with('status', "Template “{$template->name}” saved.");
    }

    public function toggle(DocumentTemplate $template): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $template->update(['is_active' => ! $template->is_active]);

        return back()->with('status', $template->is_active ? 'Template activated.' : 'Template deactivated. It is no longer offered for new documents.');
    }

    public function move(DocumentTemplate $template, string $direction): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $moved = DB::transaction(function () use ($template, $direction) {
            $siblings = DocumentTemplate::query()->ordered()->lockForUpdate()->get()->values();
            $index = $siblings->search(fn (DocumentTemplate $t) => $t->id === $template->id);
            $swap = $direction === 'up' ? $index - 1 : $index + 1;
            if ($index === false || ! isset($siblings[$swap])) {
                return false;
            }

            $ordered = $siblings->all();
            [$ordered[$index], $ordered[$swap]] = [$ordered[$swap], $ordered[$index]];
            foreach (array_values($ordered) as $position => $item) {
                if ($item->sort_order !== $position + 1) {
                    $item->sort_order = $position + 1;
                    $item->saveQuietly();
                }
            }

            return true;
        });

        if ($moved) {
            Audit::record('document_template.reordered', $template, ['direction' => $direction]);
        }

        return back();
    }

    public function destroy(DocumentTemplate $template): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $template->delete();

        return redirect()->route('admin.templates.index')->with('status', "Template “{$template->name}” deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?DocumentTemplate $template): array
    {
        return [
            'name' => ['required', 'string', 'max:120', new NoSecrets],
            'description' => ['nullable', 'string', 'max:255', new NoSecrets],
            'category_id' => ['nullable', $this->options()->rule('document_category', $template?->category_id)],
            'content' => ['required', 'string', 'max:'.self::MAX_CONTENT, new NoSecrets],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'content' => str_replace(["\r\n", "\r"], "\n", $validated['content']),
        ];
    }
}
