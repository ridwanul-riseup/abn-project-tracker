<?php

namespace App\Support\Reports;

use App\Models\Option;
use App\Models\Task;
use App\Support\Options\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use stdClass;

/**
 * Aggregates task counts per status for reports, in one grouped query.
 * The caller passes an already access-restricted task query.
 */
class TaskReport
{
    public const DUE_SOON_DAYS = 7;

    private const GROUP_SELECTS = [
        'product_id' => 'tasks.product_id as grp',
        'module_id' => 'tasks.module_id as grp',
        'assignee_id' => 'tasks.assignee_id as grp',
        'application_id' => 'tasks.application_id as grp',
        'type_id' => 'tasks.type_id as grp',
        'priority_id' => 'tasks.priority_id as grp',
    ];

    public function __construct(private readonly Options $options) {}

    /**
     * @param  Builder<Task>  $tasks
     */
    public function total(Builder $tasks): TaskSummary
    {
        $summary = new TaskSummary;
        foreach ($this->rows($tasks, null) as $row) {
            $this->addRow($summary, $row);
        }

        return $summary;
    }

    /**
     * Summaries keyed by the group column value (0 for NULL).
     *
     * @param  Builder<Task>  $tasks
     * @return array<int, TaskSummary>
     */
    public function by(Builder $tasks, string $column): array
    {
        if (! isset(self::GROUP_SELECTS[$column])) {
            throw new InvalidArgumentException("Cannot group tasks by [{$column}].");
        }

        $summaries = [];
        foreach ($this->rows($tasks, $column) as $row) {
            $key = (int) ($row->grp ?? 0);
            $this->addRow($summaries[$key] ??= new TaskSummary, $row);
        }

        return $summaries;
    }

    /**
     * Status columns to display: active statuses in their configured order,
     * plus inactive ones that still have tasks in the given summaries.
     *
     * @param  iterable<TaskSummary>  $summaries
     * @return Collection<int, Option>
     */
    public function statusColumns(iterable $summaries): Collection
    {
        $used = [];
        foreach ($summaries as $summary) {
            foreach ($summary->statuses as $statusId => $count) {
                if ($count > 0) {
                    $used[$statusId] = true;
                }
            }
        }

        return $this->options->forFilter('task_status')
            ->filter(fn (Option $o) => $o->is_active || isset($used[$o->id]))
            ->values();
    }

    /**
     * @param  Builder<Task>  $tasks
     * @return Collection<int, stdClass>
     */
    private function rows(Builder $tasks, ?string $column): Collection
    {
        $today = now()->toDateString();
        $soon = now()->addDays(self::DUE_SOON_DAYS)->toDateString();

        $query = (clone $tasks)->toBase()->reorder()
            ->selectRaw($column === null ? 'NULL as grp' : self::GROUP_SELECTS[$column])
            ->selectRaw('tasks.status_id as status_id')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->selectRaw('SUM(CASE WHEN tasks.due_date < ? THEN 1 ELSE 0 END) as overdue_count', [$today])
            ->selectRaw('SUM(CASE WHEN tasks.due_date >= ? AND tasks.due_date <= ? THEN 1 ELSE 0 END) as due_soon_count', [$today, $soon])
            ->selectRaw('SUM(CASE WHEN tasks.assignee_id IS NULL THEN 1 ELSE 0 END) as unassigned_count')
            ->groupBy('tasks.status_id');

        if ($column) {
            $query->groupBy("tasks.{$column}");
        }

        return $query->get();
    }

    private function addRow(TaskSummary $summary, stdClass $row): void
    {
        $statusId = (int) $row->status_id;

        $summary->add(
            $statusId,
            $this->options->find($statusId)->behavior ?? 'open',
            (int) $row->aggregate_count,
            (int) $row->overdue_count,
            (int) $row->due_soon_count,
            (int) $row->unassigned_count,
        );
    }
}
