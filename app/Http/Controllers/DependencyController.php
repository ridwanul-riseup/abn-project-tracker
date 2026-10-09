<?php

namespace App\Http\Controllers;

use App\Models\ComponentDependency;
use App\Models\Document;
use App\Models\Repository;
use App\Rules\NoSecrets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DependencyController extends Controller
{
    public function index(Request $request): View
    {
        $query = ComponentDependency::query()->visibleTo($this->user())
            ->with(['source', 'target', 'dependencyType', 'criticality', 'status']);

        if ($q = $request->string('q')->trim()->toString()) {
            $query->where(fn ($w) => $w->where('code', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%")->orWhere('failure_impact', 'like', "%{$q}%"));
        }
        foreach (['type' => 'dependency_type_id', 'criticality' => 'criticality_id', 'status' => 'status_id'] as $param => $column) {
            if ($value = $request->integer($param)) {
                $query->where($column, $value);
            }
        }
        if ($productId = $request->integer('product')) {
            $repoIds = Repository::query()->where('product_id', $productId)->pluck('id');
            $query->where(fn ($w) => $w
                ->where(fn ($s) => $s->where('source_type', 'repository')->whereIn('source_id', $repoIds))
                ->orWhere(fn ($t) => $t->where('target_type', 'repository')->whereIn('target_id', $repoIds)));
        }
        if ($component = $request->string('component')->toString()) {
            [$type, $id] = array_pad(explode(':', $component, 2), 2, null);
            $query->where(fn ($w) => $w->where(['source_type' => $type, 'source_id' => (int) $id])->orWhere(fn ($t) => $t->where(['target_type' => $type, 'target_id' => (int) $id])));
        }

        return view('dependencies.index', [
            'dependencies' => $query->orderBy('code')->paginate($this->perPage())->withQueryString(),
            'products' => $this->productChoices(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('dependencies.create');

        return view('dependencies.form', [
            'dependency' => new ComponentDependency,
            'preselectedSource' => $request->string('source')->toString(),
            ...$this->formData(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('dependencies.create');
        $data = $this->validated($request);
        $data['code'] = $this->nextCode();
        $dependency = ComponentDependency::query()->create($data);

        return redirect()->route('dependencies.index', ['component' => $dependency->source_type.':'.$dependency->source_id])
            ->with('status', "Dependency {$dependency->code} recorded.");
    }

    public function edit(ComponentDependency $dependency): View
    {
        $this->authorizeView($dependency);

        return view('dependencies.form', [
            'dependency' => $dependency->load(['source', 'target']),
            'preselectedSource' => $dependency->source_type.':'.$dependency->source_id,
            'readOnly' => ! Gate::allows('update', $dependency),
            ...$this->formData(),
        ]);
    }

    public function update(Request $request, ComponentDependency $dependency): RedirectResponse
    {
        $this->authorizeView($dependency);
        Gate::authorize('update', $dependency);
        $dependency->update($this->validated($request, $dependency));

        return redirect()->route('dependencies.edit', $dependency)->with('status', 'Dependency updated.');
    }

    public function destroy(ComponentDependency $dependency): RedirectResponse
    {
        $this->authorizeView($dependency);
        Gate::authorize('delete', $dependency);
        $dependency->delete();

        return redirect()->route('dependencies.index')->with('status', "Dependency {$dependency->code} deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ComponentDependency $dependency = null): array
    {
        $options = $this->options();
        $request->validate([
            'source' => ['required', 'string', 'max:60'],
            'target' => ['required', 'string', 'max:60', 'different:source'],
            'dependency_type_id' => ['nullable', $options->rule('dependency_type', $dependency?->dependency_type_id)],
            'criticality_id' => ['nullable', $options->rule('criticality', $dependency?->criticality_id)],
            'status_id' => ['nullable', $options->rule('dependency_status', $dependency?->status_id)],
            'description' => ['nullable', 'string', 'max:5000', new NoSecrets],
            'failure_impact' => ['nullable', 'string', 'max:5000'],
            'document_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000', new NoSecrets],
        ]);

        $source = $this->resolveComponent($request->string('source')->toString(), 'source');
        $target = $this->resolveComponent($request->string('target')->toString(), 'target');

        // The dependency permission and write access are required on the source; the target only has to be readable.
        if (! Gate::allows('create-in', [$dependency ? 'dependencies.update' : 'dependencies.create', $source])) {
            throw ValidationException::withMessages(['source' => 'You need write access to the source component.']);
        }

        $duplicate = ComponentDependency::query()
            ->where(['source_type' => $source->getMorphClass(), 'source_id' => $source->getKey(), 'target_type' => $target->getMorphClass(), 'target_id' => $target->getKey()])
            ->when($dependency, fn ($q) => $q->whereKeyNot($dependency->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['target' => 'This dependency is already recorded.']);
        }

        $documentId = $request->integer('document_id') ?: null;
        if ($documentId !== null && ! $this->access()->canRead($this->user(), Document::query()->find($documentId))) {
            throw ValidationException::withMessages(['document_id' => 'The selected document is invalid.']);
        }

        return [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->getKey(),
            'dependency_type_id' => $request->input('dependency_type_id') ?: null,
            'criticality_id' => $request->input('criticality_id') ?: null,
            'status_id' => $request->input('status_id') ?: null,
            'description' => $request->input('description'),
            'failure_impact' => $request->input('failure_impact'),
            'document_id' => $documentId,
            'notes' => $request->input('notes'),
        ];
    }

    private function resolveComponent(string $value, string $field): Model
    {
        [$type, $id] = array_pad(explode(':', $value, 2), 2, null);

        if (! isset(ComponentDependency::COMPONENT_TYPES[$type]) || ! ctype_digit((string) $id)) {
            throw ValidationException::withMessages([$field => 'Select a valid component.']);
        }

        /** @var class-string<Model> $class */
        $class = Model::getActualClassNameForMorph($type);
        $model = $class::query()->find((int) $id);

        if ($model === null || ! $this->access()->canRead($this->user(), $model)) {
            throw ValidationException::withMessages([$field => 'Select a valid component.']);
        }

        return $model;
    }

    private function nextCode(): string
    {
        $max = ComponentDependency::query()->where('code', 'like', 'DEP-%')->pluck('code')
            ->map(fn ($c) => (int) substr($c, 4))->max() ?? 0;

        return sprintf('DEP-%03d', $max + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        $user = $this->user();
        $components = [];

        foreach (ComponentDependency::COMPONENT_TYPES as $type => $label) {
            /** @var class-string<Model> $class */
            $class = Model::getActualClassNameForMorph($type);
            $items = $this->access()->restrict($class::query(), $user)->orderBy('code')->get();
            $components[$label] = $items->mapWithKeys(fn (Model $m) => [$type.':'.$m->getKey() => ComponentDependency::componentLabel($m)])->all();
        }

        return [
            'components' => $components,
            'documents' => Document::query()->visibleTo($user)->notArchived()->orderBy('title')->get()->mapWithKeys(fn ($d) => [$d->id => $d->title]),
        ];
    }
}
