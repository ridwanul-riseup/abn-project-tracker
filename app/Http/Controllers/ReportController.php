<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\Product;
use App\Models\Task;
use App\Models\User;
use App\Support\Reports\TaskReport;
use App\Support\Reports\TaskSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Read-only progress reports. Every figure is computed from tasks the current
 * user may read; products, modules and people outside their access are not listed.
 */
class ReportController extends Controller
{
    private const TASK_FILTERS = ['type_id', 'priority_id', 'assignee_id'];

    public function __construct(private readonly TaskReport $report) {}

    public function index(Request $request): View
    {
        $user = $this->user();
        $products = Product::query()->visibleTo($user)->with('status')->orderBy('code');
        if (! $request->boolean('archived')) {
            $products->notArchived();
        }

        $tasks = $this->tasks($request)->whereIn('tasks.product_id', (clone $products)->reorder()->select('products.id'));
        $total = $this->report->total($tasks);

        return view('reports.index', [
            'products' => $products->paginate($this->perPage())->withQueryString(),
            'summaries' => $this->report->by($tasks, 'product_id'),
            'total' => $total,
            'columns' => $this->report->statusColumns([$total]),
            'moduleCounts' => Module::query()->visibleTo($user)->notArchived()
                ->selectRaw('product_id, COUNT(*) as aggregate_count')->groupBy('product_id')
                ->pluck('aggregate_count', 'product_id'),
            ...$this->filterData($request),
        ]);
    }

    public function product(Request $request, Product $product): View
    {
        $this->authorizeView($product);
        $user = $this->user();

        $modules = Module::query()->visibleTo($user)->where('product_id', $product->id)
            ->with(['status', 'priority', 'application'])
            ->orderByRaw('archived_at IS NOT NULL')->orderBy('code');
        $visibleModuleIds = array_flip((clone $modules)->reorder()->pluck('modules.id')->map(fn ($id) => (int) $id)->all());

        $tasks = $this->tasks($request)->where('tasks.product_id', $product->id);
        $total = $this->report->total($tasks);
        $byModule = $this->report->by($tasks, 'module_id');

        // Tasks linked to modules the user cannot see are counted, but the module is not named.
        $outside = new TaskSummary;
        foreach ($byModule as $moduleId => $summary) {
            if ($moduleId !== 0 && ! isset($visibleModuleIds[$moduleId])) {
                $outside->merge($summary);
            }
        }

        $attention = fn () => Task::query()->visibleTo($user)->where('product_id', $product->id)
            ->whereIn('status_id', $this->options()->statusIdsWithBehavior(['open', 'blocked']))
            ->with(['status', 'priority', 'assignee', 'module'])
            ->tap(fn (Builder $q) => $this->applyTaskFilters($q, $request));
        $today = now()->toDateString();

        return view('reports.product', [
            'product' => $product->load('status'),
            'modules' => $modules->paginate($this->perPage())->withQueryString(),
            'summaries' => $byModule,
            'unlinked' => $byModule[0] ?? new TaskSummary,
            'outside' => $outside,
            'total' => $total,
            'columns' => $this->report->statusColumns([$total]),
            'byType' => $this->report->by($tasks, 'type_id'),
            'byPriority' => $this->report->by($tasks, 'priority_id'),
            'overdueTasks' => $attention()->where('due_date', '<', $today)->orderBy('due_date')->limit(8)->get(),
            'dueSoonTasks' => $attention()->whereBetween('due_date', [$today, now()->addDays(TaskReport::DUE_SOON_DAYS)->toDateString()])->orderBy('due_date')->limit(8)->get(),
            'blockedTasks' => $attention()->whereIn('status_id', $this->options()->statusIdsWithBehavior(['blocked']))->latest('updated_at')->limit(8)->get(),
            ...$this->filterData($request),
        ]);
    }

    public function modules(Request $request): View
    {
        $modules = Module::query()->visibleTo($this->user())
            ->with(['product', 'status', 'priority'])
            ->orderBy('product_id')->orderBy('code');
        if ($productId = $request->integer('product_id')) {
            $modules->where('product_id', $productId);
        }
        if ($statusId = $request->integer('module_status_id')) {
            $modules->where('status_id', $statusId);
        }
        if (! $request->boolean('archived')) {
            $modules->notArchived();
        }

        $tasks = $this->tasks($request)->whereIn('tasks.module_id', (clone $modules)->reorder()->select('modules.id'));
        $total = $this->report->total($tasks);

        return view('reports.modules', [
            'modules' => $modules->paginate($this->perPage())->withQueryString(),
            'summaries' => $this->report->by($tasks, 'module_id'),
            'total' => $total,
            'columns' => $this->report->statusColumns([$total]),
            ...$this->filterData($request),
        ]);
    }

    public function workload(Request $request): View
    {
        $tasks = $this->tasks($request);
        if ($productId = $request->integer('product_id')) {
            $tasks->where('tasks.product_id', $productId);
        }
        $total = $this->report->total($tasks);
        $byAssignee = $this->report->by($tasks, 'assignee_id');

        $people = User::query()->whereIn('id', array_keys(array_filter($byAssignee, fn ($s, $id) => $id !== 0, ARRAY_FILTER_USE_BOTH)))
            ->with('role')->orderBy('name');

        return view('reports.workload', [
            'people' => $people->paginate($this->perPage())->withQueryString(),
            'summaries' => $byAssignee,
            'unassigned' => $byAssignee[0] ?? new TaskSummary,
            'total' => $total,
            'columns' => $this->report->statusColumns([$total]),
            ...$this->filterData($request),
        ]);
    }

    /**
     * @return Builder<Task>
     */
    private function tasks(Request $request): Builder
    {
        return Task::query()->visibleTo($this->user())->tap(fn (Builder $q) => $this->applyTaskFilters($q, $request));
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applyTaskFilters(Builder $query, Request $request): void
    {
        foreach (self::TASK_FILTERS as $filter) {
            if ($value = $request->integer($filter)) {
                $query->where("tasks.{$filter}", $value);
            }
        }
    }

    /**
     * Filter choices plus the active task filters, which drill-down links carry over to the task list.
     *
     * @return array{productChoices: array<int, string>, assignees: Collection<int, string>, taskFilters: array<string, int>}
     */
    private function filterData(Request $request): array
    {
        $taskFilters = [];
        foreach (self::TASK_FILTERS as $filter) {
            if ($value = $request->integer($filter)) {
                $taskFilters[$filter] = $value;
            }
        }

        return [
            'productChoices' => $this->productChoices(),
            'assignees' => User::query()->active()->orderBy('name')->pluck('name', 'id'),
            'taskFilters' => $taskFilters,
        ];
    }
}
