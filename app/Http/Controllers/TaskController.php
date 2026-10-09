<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Document;
use App\Models\Module;
use App\Models\Product;
use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use App\Rules\NoSecrets;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->user();
        $query = Task::query()->visibleTo($user)
            ->with(['product', 'repository', 'module', 'type', 'status', 'priority', 'assignee']);

        foreach (['product_id', 'repository_id', 'module_id', 'status_id', 'priority_id', 'type_id', 'assignee_id'] as $filter) {
            if ($value = $request->integer($filter)) {
                $query->where($filter, $value);
            }
        }
        if ($request->boolean('mine')) {
            $query->where('assignee_id', $user->id);
        }

        $state = $request->string('state', 'open')->toString();
        match ($state) {
            'blocked' => $query->whereIn('status_id', $this->options()->statusIdsWithBehavior(['blocked'])),
            'closed' => $query->whereIn('status_id', $this->options()->statusIdsWithBehavior(['closed'])),
            'all' => null,
            default => $query->whereIn('status_id', $this->options()->statusIdsWithBehavior(['open', 'blocked'])),
        };

        if ($q = $request->string('q')->trim()->toString()) {
            $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('key', 'like', "%{$q}%"));
        }

        $sort = $request->string('sort', 'updated')->toString();
        match ($sort) {
            'due' => $query->orderByRaw('due_date IS NULL')->orderBy('due_date'),
            'key' => $query->orderBy('product_id')->orderBy('number'),
            default => $query->latest('updated_at'),
        };

        return view('tasks.index', [
            'tasks' => $query->paginate($this->perPage())->withQueryString(),
            'products' => $this->productChoices(),
            'assignees' => User::query()->active()->orderBy('name')->pluck('name', 'id'),
            'state' => $state,
            'canCreate' => Gate::allows('tasks.create'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('tasks.create');

        $task = new Task([
            'product_id' => $request->integer('product_id') ?: null,
            'repository_id' => $request->integer('repository_id') ?: null,
            'module_id' => $request->integer('module_id') ?: null,
            'type_id' => $this->options()->defaultOrFirstId('task_type'),
            'status_id' => $this->options()->initialTaskStatusId(),
            'priority_id' => $this->options()->defaultOrFirstId('priority'),
        ]);
        if (! $task->product_id && $task->module_id) {
            $task->product_id = Module::query()->whereKey($task->module_id)->value('product_id');
        } elseif (! $task->product_id && $task->repository_id) {
            $task->product_id = Repository::query()->whereKey($task->repository_id)->value('product_id');
        }

        return view('tasks.form', $this->formData($task));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('tasks.create');

        [$attributes, $documentIds] = $this->validated($request, null);
        $probe = new Task($attributes);
        Gate::authorize('tasks.create-in', $this->scopeFor($probe));
        $this->checkAssignee($probe);

        $task = DB::transaction(function () use ($attributes, $documentIds) {
            $product = Product::query()->lockForUpdate()->whereKey($attributes['product_id'])->firstOrFail();
            $number = (int) Task::query()->where('product_id', $product->id)->max('number') + 1;

            $task = Task::query()->create([
                ...$attributes,
                'number' => $number,
                'key' => $product->code.'-'.$number,
                'reporter_id' => $this->user()->id,
                'status_id' => $attributes['status_id'] ?? $this->options()->initialTaskStatusId(),
            ]);
            $task->documents()->sync($documentIds);

            return $task;
        });

        return redirect()->route('tasks.show', $task)->with('status', "Task {$task->key} created.");
    }

    public function show(Task $task): View
    {
        $this->authorizeView($task);
        $task->load(['product', 'application', 'repository', 'module', 'type', 'status', 'priority', 'assignee', 'reporter']);

        return view('tasks.show', [
            'task' => $task,
            'comments' => $task->comments()->with('user')->oldest()->get(),
            'documents' => $task->documents()->visibleTo($this->user())->get(),
            'hiddenDocuments' => $task->documents()->count() - $task->documents()->visibleTo($this->user())->count(),
        ]);
    }

    public function edit(Task $task): View
    {
        $this->authorizeView($task);
        Gate::authorize('update', $task);

        return view('tasks.form', $this->formData($task));
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeView($task);
        Gate::authorize('update', $task);

        [$attributes, $documentIds] = $this->validated($request, $task);
        $probe = (clone $task)->fill($attributes);

        $scopeKeys = ['application_id', 'repository_id', 'module_id'];
        if ($probe->only($scopeKeys) != $task->only($scopeKeys)) {
            $scope = $this->scopeFor($probe);
            abort_unless($this->access()->canWrite($this->user(), $scope), 403);
        }
        $this->checkAssignee($probe);

        DB::transaction(function () use ($task, $attributes, $documentIds) {
            $task->update($attributes);
            $this->syncDocuments($task, $documentIds);
        });

        return redirect()->route('tasks.show', $task)->with('status', 'Task updated.');
    }

    /**
     * Status / blocker / completion notes: allowed for managers and for the assignee.
     */
    public function progress(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeView($task);
        Gate::authorize('updateProgress', $task);

        $validated = $request->validate([
            'status_id' => ['required', $this->options()->rule('task_status', $task->status_id)],
            'blocker_description' => ['nullable', 'string', 'max:5000', new NoSecrets],
            'completion_notes' => ['nullable', 'string', 'max:5000', new NoSecrets],
        ]);
        $this->requireBlockerWhenBlocked($validated);

        $task->update([
            'status_id' => (int) $validated['status_id'],
            'blocker_description' => $validated['blocker_description'] ?? null,
            'completion_notes' => $validated['completion_notes'] ?? null,
        ]);

        return redirect()->route('tasks.show', $task)->with('status', 'Progress updated.');
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<int>}
     */
    private function validated(Request $request, ?Task $task): array
    {
        $user = $this->user();
        $readable = fn (string $class) => function (string $attribute, mixed $value, \Closure $fail) use ($class, $user) {
            if (! $this->access()->canRead($user, (is_numeric($value) ? $class::query()->find((int) $value) : null))) {
                $fail('The selected :attribute is invalid.');
            }
        };

        $rules = [
            'title' => ['required', 'string', 'max:200', new NoSecrets],
            'description' => ['nullable', 'string', 'max:20000', new NoSecrets],
            'application_id' => ['nullable', 'integer', $readable(Application::class)],
            'repository_id' => ['nullable', 'integer', $readable(Repository::class)],
            'module_id' => ['nullable', 'integer', $readable(Module::class)],
            'type_id' => ['required', $this->options()->rule('task_type', $task?->type_id)],
            'status_id' => [$task ? 'required' : 'nullable', $this->options()->rule('task_status', $task?->status_id)],
            'priority_id' => ['required', $this->options()->rule('priority', $task?->priority_id)],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'issue_url' => ['nullable', 'string', 'max:500', 'url:https,http', new NoSecrets],
            'blocker_description' => ['nullable', 'string', 'max:5000', new NoSecrets],
            'completion_notes' => ['nullable', 'string', 'max:5000', new NoSecrets],
            'document_ids' => ['nullable', 'array', 'max:50'],
            'document_ids.*' => ['integer', $readable(Document::class)],
        ];
        // The product determines the task key; it is fixed after creation.
        if ($task === null) {
            $rules['product_id'] = ['required', 'integer', $readable(Product::class)];
        }

        $validated = $request->validate($rules);
        $this->requireBlockerWhenBlocked($validated);

        $productId = $task !== null ? $task->product_id : (int) $validated['product_id'];
        foreach (['application_id' => Application::class, 'repository_id' => Repository::class, 'module_id' => Module::class] as $key => $class) {
            if (! empty($validated[$key]) && (int) $class::query()->whereKey($validated[$key])->value('product_id') !== $productId) {
                throw ValidationException::withMessages([$key => 'The selected record belongs to a different product.']);
            }
        }

        $attributes = [];
        foreach (array_keys($rules) as $key) {
            if (! str_starts_with($key, 'document_ids')) {
                $attributes[$key] = ($validated[$key] ?? null) === '' ? null : ($validated[$key] ?? null);
            }
        }
        foreach (['product_id', 'application_id', 'repository_id', 'module_id', 'type_id', 'status_id', 'priority_id', 'assignee_id'] as $key) {
            if (array_key_exists($key, $attributes) && $attributes[$key] !== null) {
                $attributes[$key] = (int) $attributes[$key];
            }
        }
        if ($task === null && ($attributes['status_id'] ?? null) === null) {
            unset($attributes['status_id']);
        }

        // Only task managers may assign; others keep the current assignee.
        if (! Gate::allows('tasks.manage')) {
            $attributes['assignee_id'] = $task?->assignee_id;
        }

        return [$attributes, array_values(array_map('intval', $validated['document_ids'] ?? []))];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function requireBlockerWhenBlocked(array $validated): void
    {
        $statusId = (int) ($validated['status_id'] ?? 0);
        if (in_array($statusId, $this->options()->statusIdsWithBehavior(['blocked']), true) && blank($validated['blocker_description'] ?? null)) {
            throw ValidationException::withMessages(['blocker_description' => 'Describe what is blocking this task.']);
        }
    }

    private function checkAssignee(Task $task): void
    {
        if (! $task->assignee_id) {
            return;
        }
        $assignee = User::query()->active()->find($task->assignee_id);
        if ($assignee === null || ! $this->access()->canRead($assignee, $task)) {
            throw ValidationException::withMessages(['assignee_id' => 'The assignee must be an active user with access to this task\'s product, repository or module.']);
        }
    }

    /**
     * Keeps links to documents the editor cannot see, since they could not have removed them knowingly.
     *
     * @param  list<int>  $documentIds
     */
    private function syncDocuments(Task $task, array $documentIds): void
    {
        $hidden = $task->documents()->pluck('documents.id')
            ->reject(fn (int $id) => $this->access()->canRead($this->user(), Document::query()->whereKey($id)->first()))
            ->all();
        $before = $task->documents()->pluck('documents.id')->sort()->values()->all();
        $task->documents()->sync(array_values(array_unique([...$documentIds, ...$hidden])));
        $after = $task->documents()->pluck('documents.id')->sort()->values()->all();
        if ($before !== $after) {
            Audit::record('task.documents_changed', $task, ['before' => $before, 'after' => $after]);
        }
    }

    private function scopeFor(Task $task): ?Model
    {
        return match (true) {
            (bool) $task->module_id => Module::query()->find($task->module_id),
            (bool) $task->repository_id => Repository::query()->find($task->repository_id),
            (bool) $task->application_id => Application::query()->find($task->application_id),
            default => Product::query()->find($task->product_id),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Task $task): array
    {
        $user = $this->user();
        $label = fn ($m) => [$m->id => $m->code.' — '.$m->name];

        return [
            'task' => $task,
            'products' => $task->exists ? [] : $this->productChoices(),
            'applications' => Application::query()->visibleTo($user)->orderBy('code')->get()->mapWithKeys($label),
            'repositories' => Repository::query()->visibleTo($user)->orderBy('code')->get()->mapWithKeys($label),
            'modules' => Module::query()->visibleTo($user)->orderBy('code')->get()->mapWithKeys($label),
            'documents' => Document::query()->visibleTo($user)->notArchived()->orderBy('title')->get()->mapWithKeys(fn ($d) => [$d->id => $d->title]),
            'selectedDocuments' => $task->exists ? $task->documents()->pluck('documents.id') : collect(),
            'assignees' => $this->assigneeChoices(),
            'canAssign' => Gate::allows('tasks.manage'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function assigneeChoices(): array
    {
        return User::query()->active()->with('role')->orderBy('name')->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->name.' ('.$u->role?->name.')'])
            ->all();
    }
}
