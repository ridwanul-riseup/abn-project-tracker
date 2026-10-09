<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModuleRequest;
use App\Models\Application;
use App\Models\ComponentDependency;
use App\Models\DatabaseReference;
use App\Models\Document;
use App\Models\Module;
use App\Models\Product;
use App\Models\Repository;
use App\Models\Task;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function index(Request $request): View
    {
        $query = Module::query()->visibleTo($this->user())
            ->with(['product', 'application', 'primaryRepository', 'status', 'priority']);

        if ($q = $request->string('q')->trim()->toString()) {
            $query->where(fn ($w) => $w->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%"));
        }
        foreach (['product' => 'product_id', 'application' => 'application_id', 'status' => 'status_id', 'priority' => 'priority_id', 'repository' => 'primary_repository_id'] as $param => $column) {
            if ($value = $request->integer($param)) {
                $query->where($column, $value);
            }
        }
        if (! $request->boolean('archived')) {
            $query->notArchived();
        }

        return view('modules.index', [
            'modules' => $query->orderBy('code')->paginate($this->perPage())->withQueryString(),
            'products' => $this->productChoices(),
        ]);
    }

    public function create(Request $request): View
    {
        $product = Product::query()->find($request->integer('product_id'));
        Gate::authorize('inventory.create-in', $product ?? $this->firstWritableProduct());

        return view('modules.form', $this->formData(new Module(['product_id' => $product?->id])));
    }

    public function store(ModuleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $related = $data['related_repository_ids'] ?? [];
        unset($data['related_repository_ids']);

        $module = DB::transaction(function () use ($data, $related) {
            $module = Module::query()->create($data);
            $module->relatedRepositories()->sync($related);

            return $module;
        });

        return redirect()->route('modules.show', $module)->with('status', 'Module created.');
    }

    public function show(Module $module): View
    {
        $this->authorizeView($module);
        $user = $this->user();
        $deps = ComponentDependency::query()->visibleTo($user)->with(['source', 'target', 'dependencyType', 'criticality']);

        return view('modules.show', [
            'module' => $module->load(['product', 'application', 'primaryRepository', 'databaseReference', 'status', 'priority']),
            'relatedRepositories' => $module->relatedRepositories()->visibleTo($user)->orderBy('code')->get(),
            'documents' => Document::query()->visibleTo($user)->notArchived()->where('module_id', $module->id)->with('category')->orderBy('title')->get(),
            'tasks' => Task::query()->visibleTo($user)->where('module_id', $module->id)->with(['status', 'priority', 'assignee'])->latest('updated_at')->limit(15)->get(),
            'dependencies' => (clone $deps)->where(fn ($q) => $q->where(['source_type' => 'module', 'source_id' => $module->id])->orWhere(fn ($w) => $w->where(['target_type' => 'module', 'target_id' => $module->id])))->get(),
            'clarifications' => $this->clarificationsFor($module),
        ]);
    }

    public function edit(Module $module): View
    {
        $this->authorizeView($module);
        Gate::authorize('update', $module);

        return view('modules.form', $this->formData($module));
    }

    public function update(ModuleRequest $request, Module $module): RedirectResponse
    {
        $data = $request->validated();
        $related = $data['related_repository_ids'] ?? [];
        unset($data['related_repository_ids']);

        DB::transaction(function () use ($module, $data, $related) {
            $before = $module->relatedRepositories()->pluck('repositories.code')->sort()->values()->all();
            $module->update($data);
            $module->relatedRepositories()->sync($related);
            $after = $module->relatedRepositories()->pluck('repositories.code')->sort()->values()->all();
            if ($before !== $after) {
                Audit::record('module.repositories_changed', $module, ['before' => $before, 'after' => $after]);
            }
        });

        return redirect()->route('modules.show', $module)->with('status', 'Module updated.');
    }

    public function archive(Module $module): RedirectResponse
    {
        $this->authorizeView($module);
        Gate::authorize('delete', $module);
        $module->update(['archived_at' => now()]);

        return back()->with('status', 'Module archived.');
    }

    public function restore(Module $module): RedirectResponse
    {
        $this->authorizeView($module);
        Gate::authorize('delete', $module);
        $module->update(['archived_at' => null]);

        return back()->with('status', 'Module restored.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Module $module): array
    {
        $user = $this->user();

        return [
            'module' => $module,
            'products' => $this->productChoices(writableOnly: true),
            'applications' => Application::query()->visibleTo($user)->notArchived()->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->code.' — '.$a->name]),
            'repositories' => Repository::query()->visibleTo($user)->notArchived()->orderBy('code')->get()->mapWithKeys(fn ($r) => [$r->id => $r->code]),
            'databases' => DatabaseReference::query()->visibleTo($user)->orderBy('code')->get()->mapWithKeys(fn ($d) => [$d->id => $d->code.' ('.$d->name.')']),
            'selectedRelated' => $module->exists ? $module->relatedRepositories()->pluck('repositories.id') : collect(),
        ];
    }
}
