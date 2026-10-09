<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Document;
use App\Models\Environment;
use App\Models\Product;
use App\Models\Repository;
use App\Rules\NoSecrets;
use App\Support\Audit;
use App\Support\Registries;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Generic CRUD for the reference registries defined in App\Support\Registries.
 *
 * @phpstan-import-type RegistryDefinition from Registries
 */
class RegistryController extends Controller
{
    public function index(Request $request, string $type): View
    {
        $definition = Registries::get($type);
        $query = $this->access()->restrict($definition['model']::query(), $this->user())->with($definition['with']);

        if ($q = $request->string('q')->trim()->toString()) {
            $query->where(function ($w) use ($definition, $q) {
                foreach ($definition['search'] as $column) {
                    $w->orWhere($column, 'like', "%{$q}%");
                }
            });
        }
        foreach ($definition['filters'] as $column) {
            if ($value = $request->integer($column)) {
                $query->where($column, $value);
            }
        }

        return view('registries.index', [
            'type' => $type,
            'definition' => $definition,
            'records' => $query->orderBy($definition['order'] ?? 'code')->paginate($this->perPage())->withQueryString(),
            'products' => $this->productChoices(),
            'repositories' => $this->choices('repository'),
            'canCreate' => $this->canCreateAny($definition),
        ]);
    }

    public function create(Request $request, string $type): View
    {
        $definition = Registries::get($type);
        abort_unless($this->canCreateAny($definition), 403);

        $record = new $definition['model'];
        foreach (['product_id', 'repository_id', 'source_product_id'] as $preset) {
            if ($request->filled($preset)) {
                $record->setAttribute($preset, $request->integer($preset));
            }
        }

        return view('registries.form', $this->formData($type, $definition, $record));
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $definition = Registries::get($type);
        [$data, $many] = $this->validated($request, $definition, null);
        $this->authorizeScope($definition, $data);

        $record = DB::transaction(function () use ($definition, $data, $many) {
            $record = $definition['model']::query()->create($data);
            foreach ($many as $relation => $ids) {
                $record->{$relation}()->sync($ids);
            }

            return $record;
        });

        return redirect()->route('registries.show', [$type, $record->getKey()])->with('status', $definition['singular'].' created.');
    }

    public function show(string $type, int $id): View
    {
        $definition = Registries::get($type);
        $record = $this->findReadable($definition, $id);

        return view('registries.show', [
            'type' => $type,
            'definition' => $definition,
            'record' => $record->load($definition['with']),
            'clarifications' => $this->clarificationsFor($record),
        ]);
    }

    public function edit(string $type, int $id): View
    {
        $definition = Registries::get($type);
        $record = $this->findReadable($definition, $id);
        Gate::authorize('update', $record);

        return view('registries.form', $this->formData($type, $definition, $record));
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $definition = Registries::get($type);
        $record = $this->findReadable($definition, $id);
        Gate::authorize('update', $record);

        [$data, $many] = $this->validated($request, $definition, $record);
        $scope = $definition['scope'];
        if ($scope !== null && (int) ($data[$scope] ?? 0) !== (int) $record->getAttribute($scope)) {
            $this->authorizeScope($definition, $data);
        }

        DB::transaction(function () use ($record, $data, $many) {
            $record->update($data);
            foreach ($many as $relation => $ids) {
                $before = $record->{$relation}()->pluck('code')->sort()->values()->all();
                $record->{$relation}()->sync($ids);
                $after = $record->{$relation}()->pluck('code')->sort()->values()->all();
                if ($before !== $after) {
                    Audit::record($record->getMorphClass().'.'.$relation.'_changed', $record, ['before' => $before, 'after' => $after]);
                }
            }
        });

        return redirect()->route('registries.show', [$type, $id])->with('status', $definition['singular'].' updated.');
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        $definition = Registries::get($type);
        $record = $this->findReadable($definition, $id);
        Gate::authorize('delete', $record);

        try {
            DB::transaction(fn () => $record->delete());
        } catch (QueryException) {
            return back()->with('error', 'This record is referenced by other records and cannot be deleted. Change its status instead.');
        }

        return redirect()->route('registries.index', $type)->with('status', $definition['singular'].' deleted.');
    }

    /**
     * @param  RegistryDefinition  $definition
     */
    private function findReadable(array $definition, int $id): Model
    {
        $record = $definition['model']::query()->find($id);
        if ($record === null || ! $this->access()->canRead($this->user(), $record)) {
            abort(404);
        }

        return $record;
    }

    /**
     * Creation requires inventory.write and write access to the record's scope.
     *
     * @param  RegistryDefinition  $definition
     * @param  array<string, mixed>  $data
     */
    private function authorizeScope(array $definition, array $data): void
    {
        $scopeColumn = $definition['scope'];
        $scopeModel = null;

        if ($scopeColumn !== null && ! empty($data[$scopeColumn])) {
            $class = $scopeColumn === 'repository_id' ? Repository::class : Product::class;
            $scopeModel = $class::query()->find($data[$scopeColumn]);
        }

        Gate::authorize('inventory.create-in', $scopeModel);
    }

    /**
     * @param  RegistryDefinition  $definition
     */
    private function canCreateAny(array $definition): bool
    {
        if (! Gate::allows('inventory.write')) {
            return false;
        }
        if ($this->access()->hasGlobalWrite($this->user())) {
            return true;
        }
        if ($definition['scope'] === null) {
            return false;
        }

        $candidates = $definition['scope'] === 'repository_id'
            ? Repository::query()->visibleTo($this->user())->get()
            : Product::query()->visibleTo($this->user())->get();

        return $candidates->contains(fn (Model $m) => $this->access()->canWrite($this->user(), $m));
    }

    /**
     * @param  RegistryDefinition  $definition
     * @return array{0: array<string, mixed>, 1: array<string, list<int>>}
     */
    private function validated(Request $request, array $definition, ?Model $record): array
    {
        $user = $this->user();
        $readable = fn (string $class) => function (string $attribute, mixed $value, Closure $fail) use ($class, $user) {
            if (! $this->access()->canRead($user, (is_numeric($value) ? $class::query()->find((int) $value) : null))) {
                $fail('The selected :attribute is invalid.');
            }
        };

        $rules = [];
        foreach ($definition['fields'] as $field) {
            $name = $field['name'];
            $base = [empty($field['required']) ? 'nullable' : 'required'];
            $rules[$name] = match ($field['type']) {
                'text' => [...$base, 'string', 'max:'.($field['max'] ?? 255), new NoSecrets],
                'textarea' => [...$base, 'string', 'max:10000', new NoSecrets],
                'url' => [...$base, 'string', 'max:'.($field['max'] ?? 500), 'url:https,http', new NoSecrets],
                'date' => [...$base, 'date'],
                'number' => [...$base, 'integer', 'min:0', 'max:100000'],
                'option' => [...$base, $this->options()->rule($field['group'] ?? '', $record?->getAttribute($name))],
                'product' => [...$base, 'integer', $readable(Product::class)],
                'application' => [...$base, 'integer', $readable(Application::class)],
                'repository' => [...$base, 'integer', $readable(Repository::class)],
                'document' => [...$base, 'integer', $readable(Document::class)],
                'environment' => [...$base, 'integer', Rule::exists('environments', 'id')],
                'repositories' => ['nullable', 'array'],
                default => $base,
            };
            if ($field['type'] === 'repositories') {
                $rules[$name.'.*'] = ['integer', $readable(Repository::class)];
            }
        }

        $codeRule = Rule::unique((new $definition['model'])->getTable(), 'code');
        if ($record !== null) {
            $codeRule->ignore($record->getKey());
        }
        $rules['code'][] = $codeRule;
        $rules['code'][] = 'regex:/^[A-Za-z0-9][A-Za-z0-9_.-]*$/';

        $validated = $request->validate($rules);

        $data = [];
        $many = [];
        foreach ($definition['fields'] as $field) {
            $value = $validated[$field['name']] ?? null;
            if ($field['type'] === 'repositories') {
                $many[$field['relation'] ?? $field['name']] = array_values(array_map('intval', $value ?? []));
            } else {
                $data[$field['name']] = $value === '' ? null : $value;
            }
        }
        if (array_key_exists('sort_order', $data)) {
            $data['sort_order'] = (int) $data['sort_order'];
        }

        return [$data, $many];
    }

    /**
     * @param  RegistryDefinition  $definition
     * @return array<string, mixed>
     */
    private function formData(string $type, array $definition, Model $record): array
    {
        $choices = [];
        foreach ($definition['fields'] as $field) {
            if (in_array($field['type'], ['product', 'application', 'repository', 'document', 'environment', 'repositories'], true)) {
                $choices[$field['name']] = $this->choices($field['type'] === 'repositories' ? 'repository' : $field['type']);
            }
        }

        return compact('type', 'definition', 'record', 'choices');
    }

    /**
     * @return array<int, string>
     */
    private function choices(string $kind): array
    {
        $user = $this->user();

        return match ($kind) {
            'product' => $this->productChoices(),
            'application' => Application::query()->visibleTo($user)->orderBy('code')->get()->mapWithKeys(fn ($m) => [$m->id => $m->code.' — '.$m->name])->all(),
            'repository' => Repository::query()->visibleTo($user)->orderBy('code')->get()->mapWithKeys(fn ($m) => [$m->id => $m->code])->all(),
            'document' => Document::query()->visibleTo($user)->notArchived()->orderBy('title')->get()->mapWithKeys(fn ($m) => [$m->id => $m->title])->all(),
            'environment' => Environment::query()->orderBy('sort_order')->get()->mapWithKeys(fn ($m) => [$m->id => $m->name.' ('.$m->code.')'])->all(),
            default => [],
        };
    }
}
