<?php

namespace App\Support\Options;

use App\Models\Option;
use App\Models\OptionGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Request-cached access to configurable dropdown options.
 */
class Options
{
    /** @var Collection<int, Option>|null */
    private ?Collection $options = null;

    /** @var array<string, int>|null */
    private ?array $groupIds = null;

    public function flush(): void
    {
        $this->options = null;
        $this->groupIds = null;
    }

    /**
     * Active options of a group, plus any explicitly included (possibly inactive) IDs.
     *
     * @return Collection<int, Option>
     */
    public function for(string $group, int|string|null ...$include): Collection
    {
        $groupId = $this->groupId($group);
        $include = array_map('intval', array_filter($include, fn ($id) => $id !== null && $id !== ''));

        return $this->all()
            ->filter(fn (Option $o) => $o->option_group_id === $groupId && ($o->is_active || in_array($o->id, $include, true)))
            ->sortBy([['sort_order', 'asc'], ['label', 'asc']])
            ->values();
    }

    public function find(int|string|null $id): ?Option
    {
        return $id === null || $id === '' ? null : $this->all()->get((int) $id);
    }

    public function idFor(string $group, string $value): ?int
    {
        $groupId = $this->groupId($group);

        return $this->all()->first(fn (Option $o) => $o->option_group_id === $groupId && $o->value === $value)?->id;
    }

    /**
     * IDs of task statuses with the given behavior(s).
     *
     * @param  list<string>  $behaviors
     * @return list<int>
     */
    public function statusIdsWithBehavior(array $behaviors): array
    {
        $groupId = $this->groupId('task_status');

        return array_values($this->all()
            ->filter(fn (Option $o) => $o->option_group_id === $groupId && in_array($o->behavior, $behaviors, true))
            ->sortBy('sort_order')
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    /**
     * Validation rule: the ID must belong to the group and be active
     * (an inactive option is accepted only if it is the record's current value).
     */
    public function rule(string $group, int|string|null $current = null): Exists
    {
        $groupId = $this->groupId($group);

        return Rule::exists('options', 'id')->where(function ($query) use ($groupId, $current) {
            $query->where('option_group_id', $groupId)
                ->where(function ($q) use ($current) {
                    $q->where('is_active', true);
                    if ($current !== null && $current !== '') {
                        $q->orWhere('id', (int) $current);
                    }
                });
        });
    }

    /**
     * Count of records referencing an option, per usage location.
     *
     * @return list<array{label: string, table: string, column: string, count: int}>
     */
    public function usage(Option $option): array
    {
        $groupKey = $option->group->key;
        $result = [];

        foreach (OptionCatalog::usage($groupKey) as [$table, $column, $label]) {
            $result[] = [
                'label' => $label,
                'table' => $table,
                'column' => $column,
                'count' => DB::table($table)->where($column, $option->id)->count(),
            ];
        }

        return $result;
    }

    public function usageCount(Option $option): int
    {
        return array_sum(array_column($this->usage($option), 'count'));
    }

    private function groupId(string $group): int
    {
        $this->groupIds ??= OptionGroup::query()->pluck('id', 'key')->map(fn ($id) => (int) $id)->all();

        if (! isset($this->groupIds[$group])) {
            throw new \InvalidArgumentException("Unknown option group [{$group}].");
        }

        return $this->groupIds[$group];
    }

    /**
     * @return Collection<int, Option>
     */
    private function all(): Collection
    {
        return $this->options ??= Option::query()->get()->keyBy('id');
    }
}
