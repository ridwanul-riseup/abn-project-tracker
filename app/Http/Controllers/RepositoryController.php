<?php

namespace App\Http\Controllers;

use App\Http\Requests\RepositoryRequest;
use App\Models\ApiReference;
use App\Models\Application;
use App\Models\ComponentDependency;
use App\Models\DatabaseReference;
use App\Models\Deployment;
use App\Models\Document;
use App\Models\Environment;
use App\Models\FigmaReference;
use App\Models\Integration;
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

class RepositoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = Repository::query()->visibleTo($this->user())
            ->with(['product', 'application', 'layer', 'status']);

        if ($q = $request->string('q')->trim()->toString()) {
            $query->where(fn ($w) => $w->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('url', 'like', "%{$q}%")
                ->orWhere('module_label', 'like', "%{$q}%")
                ->orWhere('tech_stack', 'like', "%{$q}%")
                ->orWhere('api_reference', 'like', "%{$q}%"));
        }
        foreach (['product' => 'product_id', 'application' => 'application_id', 'layer' => 'layer_id', 'status' => 'status_id'] as $param => $column) {
            if ($value = $request->integer($param)) {
                $query->where($column, $value);
            }
        }
        if ($request->boolean('missing_url')) {
            $query->whereNull('url');
        }
        if (! $request->boolean('archived')) {
            $query->notArchived();
        }

        return view('repositories.index', [
            'repositories' => $query->orderBy('code')->paginate($this->perPage())->withQueryString(),
            'products' => $this->productChoices(),
        ]);
    }

    public function create(Request $request): View
    {
        $product = Product::query()->find($request->integer('product_id'));
        Gate::authorize('inventory.create-in', $product ?? $this->firstWritableProduct());

        return view('repositories.form', $this->formData(new Repository([
            'product_id' => $product?->id,
            'application_id' => $request->integer('application_id') ?: null,
        ])));
    }

    public function store(RepositoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $environments = $data['environment_ids'] ?? [];
        unset($data['environment_ids']);
        if (! Gate::allows('repositories.view_sensitive')) {
            unset($data['sensitive_notes']);
        }

        $repository = DB::transaction(function () use ($data, $environments) {
            $repository = Repository::query()->create($data);
            $repository->environments()->sync($environments);

            return $repository;
        });

        return redirect()->route('repositories.show', $repository)->with('status', 'Repository registered.');
    }

    public function show(Repository $repository): View
    {
        $this->authorizeView($repository);
        $user = $this->user();
        $visibleDependencies = ComponentDependency::query()->visibleTo($user)
            ->with(['source', 'target', 'dependencyType', 'criticality', 'status']);

        return view('repositories.show', [
            'repository' => $repository->load(['product', 'application', 'layer', 'status', 'deploymentTarget', 'databaseReference', 'environments']),
            'canSeeSensitive' => Gate::allows('viewSensitive', $repository),
            'dependsOn' => $this->paginateList((clone $visibleDependencies)->where('source_type', 'repository')->where('source_id', $repository->id)->orderBy('code'), 'depends_on'),
            'usedBy' => $this->paginateList((clone $visibleDependencies)->where('target_type', 'repository')->where('target_id', $repository->id)->orderBy('code'), 'used_by'),
            'modules' => $this->paginateList(Module::query()->visibleTo($user)
                ->where(fn ($q) => $q->where('primary_repository_id', $repository->id)
                    ->orWhereHas('relatedRepositories', fn ($r) => $r->where('repositories.id', $repository->id)))
                ->orderBy('code'), 'modules'),
            'documents' => $this->paginateList(Document::query()->visibleTo($user)->notArchived()->where('repository_id', $repository->id)->orderBy('title'), 'documents'),
            'apis' => ApiReference::query()->visibleTo($user)->where('repository_id', $repository->id)->get(),
            'figma' => FigmaReference::query()->visibleTo($user)->where('repository_id', $repository->id)->get(),
            'deployments' => $this->paginateList(Deployment::query()->visibleTo($user)->where('repository_id', $repository->id)->with(['environment', 'status'])->latest('id'), 'deployments'),
            'integrations' => Integration::query()->visibleTo($user)->whereHas('repositories', fn ($q) => $q->where('repositories.id', $repository->id))->get(),
            'siblings' => $this->paginateList(Repository::query()->visibleTo($user)->whereKeyNot($repository->id)
                ->when($repository->application_id, fn ($q) => $q->where('application_id', $repository->application_id), fn ($q) => $q->whereRaw('1 = 0'))
                ->orderBy('code'), 'siblings'),
            'openTasks' => Task::query()->visibleTo($user)->where('repository_id', $repository->id)
                ->whereIn('status_id', $this->options()->statusIdsWithBehavior(['open', 'blocked']))->count(),
            'clarifications' => $this->clarificationsFor($repository),
        ]);
    }

    public function edit(Repository $repository): View
    {
        $this->authorizeView($repository);
        Gate::authorize('update', $repository);

        return view('repositories.form', $this->formData($repository));
    }

    public function update(RepositoryRequest $request, Repository $repository): RedirectResponse
    {
        $data = $request->validated();
        $environments = $data['environment_ids'] ?? [];
        unset($data['environment_ids']);

        // Users without the sensitive-notes capability can neither read nor overwrite them.
        if (! Gate::allows('viewSensitive', $repository)) {
            unset($data['sensitive_notes']);
        }

        DB::transaction(function () use ($repository, $data, $environments) {
            $before = $repository->environments()->pluck('environments.code')->sort()->values()->all();
            $repository->update($data);
            $repository->environments()->sync($environments);
            $after = $repository->environments()->pluck('environments.code')->sort()->values()->all();
            if ($before !== $after) {
                Audit::record('repository.environments_changed', $repository, ['before' => $before, 'after' => $after]);
            }
        });

        return redirect()->route('repositories.show', $repository)->with('status', 'Repository updated.');
    }

    public function archive(Repository $repository): RedirectResponse
    {
        $this->authorizeView($repository);
        Gate::authorize('delete', $repository);
        $repository->update(['archived_at' => now()]);

        return back()->with('status', 'Repository archived.');
    }

    public function restore(Repository $repository): RedirectResponse
    {
        $this->authorizeView($repository);
        Gate::authorize('delete', $repository);
        $repository->update(['archived_at' => null]);

        return back()->with('status', 'Repository restored.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Repository $repository): array
    {
        $user = $this->user();

        return [
            'repository' => $repository,
            'products' => $this->productChoices(writableOnly: true),
            'applications' => Application::query()->visibleTo($user)->notArchived()->with('product')->orderBy('code')->get()
                ->mapWithKeys(fn ($a) => [$a->id => $a->code.' — '.$a->name]),
            'databases' => DatabaseReference::query()->visibleTo($user)->orderBy('code')->get()
                ->mapWithKeys(fn ($d) => [$d->id => $d->code.' ('.$d->name.')']),
            'environments' => Environment::query()->orderBy('sort_order')->get()->mapWithKeys(fn ($e) => [$e->id => $e->name.' ('.$e->code.')']),
            'canSeeSensitive' => $repository->exists
                ? Gate::allows('viewSensitive', $repository)
                : Gate::allows('repositories.view_sensitive'),
        ];
    }
}
