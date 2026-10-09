<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Support\Audit;
use App\Support\Options\OptionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Global settings: configurable dropdown options. Values (stable keys) are
 * immutable; options in use can be deactivated but not deleted.
 */
class OptionController extends Controller
{
    public function index(): View
    {
        Gate::authorize('settings.manage');

        return view('admin.options.index', [
            'groups' => OptionGroup::query()->withCount([
                'options',
                'options as active_count' => fn ($q) => $q->where('is_active', true),
            ])->orderBy('name')->paginate($this->perPage()),
        ]);
    }

    public function show(OptionGroup $group): View
    {
        Gate::authorize('settings.manage');

        $options = $group->options()->paginate($this->perPage());

        return view('admin.options.show', [
            'group' => $group,
            'options' => $options,
            'usage' => $options->getCollection()->mapWithKeys(fn (Option $o) => [$o->id => $this->options()->usage($o)]),
            'usedIn' => OptionCatalog::usage($group->key),
        ]);
    }

    public function store(Request $request, OptionGroup $group): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $request->merge(['value' => Str::of($request->input('value') ?: $request->input('label', ''))->lower()->slug('_')->toString()]);
        $validated = $request->validate($this->rules($group) + [
            'value' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9_]*$/',
                Rule::unique('options', 'value')->where('option_group_id', $group->id)],
        ]);

        $group->options()->create([
            ...$this->attributes($group, $validated),
            'value' => $validated['value'],
            'sort_order' => (int) Option::query()->where('option_group_id', $group->id)->max('sort_order') + 1,
            'is_active' => true,
        ]);

        return back()->with('status', 'Option added.');
    }

    public function update(Request $request, Option $option): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $group = $option->group;

        $validated = $request->validate($this->rules($group));
        $attributes = $this->attributes($group, $validated);

        if ($group->supportsBehavior() && $option->is_active && $option->behavior === 'open' && $attributes['behavior'] !== 'open') {
            $this->ensureAnotherActive($option, 'open');
        }

        $clearsDefault = $option->is_default && $group->supportsBehavior() && $attributes['behavior'] !== 'open';
        $option->update($attributes + ($clearsDefault ? ['is_default' => false] : []));

        return back()->with('status', $clearsDefault
            ? 'Option updated. It is no longer the default because new tasks must start in an open status.'
            : 'Option updated.');
    }

    /**
     * Make an option the group's default selection for new records, or clear it.
     */
    public function setDefault(Option $option): RedirectResponse
    {
        Gate::authorize('settings.manage');

        if ($option->is_default) {
            $option->update(['is_default' => false]);

            return back()->with('status', "“{$option->label}” is no longer the default.");
        }

        if (! $option->is_active) {
            throw ValidationException::withMessages(['option' => 'Only active options can be the default.']);
        }
        if ($option->group->supportsBehavior() && $option->behavior !== 'open') {
            throw ValidationException::withMessages(['option' => 'The default task status must have the “open” behavior.']);
        }

        DB::transaction(function () use ($option) {
            Option::query()->where('option_group_id', $option->option_group_id)->where('is_default', true)
                ->whereKeyNot($option->id)->lockForUpdate()->get()
                ->each(fn (Option $previous) => $previous->update(['is_default' => false]));
            $option->update(['is_default' => true]);
        });

        return back()->with('status', "“{$option->label}” is now the default for new records.");
    }

    public function toggle(Option $option): RedirectResponse
    {
        Gate::authorize('settings.manage');

        if ($option->is_active) {
            $this->ensureAnotherActive($option);
            if ($option->group->supportsBehavior() && $option->behavior === 'open') {
                $this->ensureAnotherActive($option, 'open');
            }
        }

        $option->update($option->is_active ? ['is_active' => false, 'is_default' => false] : ['is_active' => true]);

        return back()->with('status', $option->is_active
            ? 'Option activated.'
            : 'Option deactivated. Existing records keep it; it is no longer offered for new selections.');
    }

    public function move(Option $option, string $direction): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $moved = DB::transaction(function () use ($option, $direction) {
            $siblings = Option::query()->where('option_group_id', $option->option_group_id)
                ->orderBy('sort_order')->orderBy('label')->lockForUpdate()->get()->values();

            $index = $siblings->search(fn (Option $o) => $o->id === $option->id);
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
        $this->options()->flush();

        if ($moved) {
            Audit::record('option.reordered', $option, ['direction' => $direction]);
        }

        return back();
    }

    public function destroy(Option $option): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $used = $this->options()->usageCount($option);
        if ($used > 0) {
            return back()->with('error', "“{$option->label}” is used by {$used} record(s) and cannot be deleted. Deactivate it instead.");
        }
        if ($option->is_active) {
            $this->ensureAnotherActive($option);
            if ($option->group->supportsBehavior() && $option->behavior === 'open') {
                $this->ensureAnotherActive($option, 'open');
            }
        }

        $option->delete();

        return back()->with('status', 'Option deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(OptionGroup $group): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', Rule::in(OptionCatalog::COLORS)],
            'behavior' => $group->supportsBehavior()
                ? ['required', Rule::in(array_keys(OptionCatalog::TASK_STATUS_BEHAVIORS))]
                : ['prohibited'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(OptionGroup $group, array $validated): array
    {
        return [
            'label' => $validated['label'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? null,
            'behavior' => $group->supportsBehavior() ? $validated['behavior'] : null,
        ];
    }

    private function ensureAnotherActive(Option $option, ?string $behavior = null): void
    {
        $exists = Option::query()
            ->where('option_group_id', $option->option_group_id)
            ->where('is_active', true)
            ->whereKeyNot($option->id)
            ->when($behavior, fn ($q) => $q->where('behavior', $behavior))
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages(['option' => $behavior
                ? 'At least one active status with the “open” behavior is required (new tasks start in it).'
                : 'Each group needs at least one active option.']);
        }
    }
}
