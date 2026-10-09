<?php

namespace App\Support\Reports;

/**
 * Task counts for one report row (a product, module, assignee or the whole selection).
 * Overdue, due-soon and unassigned counts only include tasks that are not closed.
 */
class TaskSummary
{
    /** @var array<int, int> status option ID => task count */
    public array $statuses = [];

    public int $total = 0;

    public int $open = 0;

    public int $blocked = 0;

    public int $closed = 0;

    public int $overdue = 0;

    public int $dueSoon = 0;

    public int $unassigned = 0;

    public function add(int $statusId, string $behavior, int $count, int $overdue, int $dueSoon, int $unassigned): void
    {
        $this->statuses[$statusId] = ($this->statuses[$statusId] ?? 0) + $count;
        $this->total += $count;

        match ($behavior) {
            'closed' => $this->closed += $count,
            'blocked' => $this->blocked += $count,
            default => $this->open += $count,
        };

        if ($behavior !== 'closed') {
            $this->overdue += $overdue;
            $this->dueSoon += $dueSoon;
            $this->unassigned += $unassigned;
        }
    }

    public function merge(self $other): void
    {
        foreach ($other->statuses as $statusId => $count) {
            $this->statuses[$statusId] = ($this->statuses[$statusId] ?? 0) + $count;
        }
        foreach (['total', 'open', 'blocked', 'closed', 'overdue', 'dueSoon', 'unassigned'] as $field) {
            $this->{$field} += $other->{$field};
        }
    }

    public function count(int $statusId): int
    {
        return $this->statuses[$statusId] ?? 0;
    }

    /** Active work: open plus blocked. */
    public function active(): int
    {
        return $this->open + $this->blocked;
    }

    /** Share of tasks that are closed, 0–100. */
    public function progress(): int
    {
        return $this->total === 0 ? 0 : (int) floor($this->closed * 100 / $this->total);
    }
}
