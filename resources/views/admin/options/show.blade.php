@php use App\Support\Options\OptionCatalog; @endphp
<x-layouts.app :title="$group->name" :breadcrumbs="['Global settings' => route('admin.options.index'), $group->name => null]">
    <x-page-header :title="$group->name" :subtitle="$group->description" />
    @error('option')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

    <p class="small text-muted">Used in: @foreach ($usedIn as [$table, $column, $label]){{ $label }}@if (! $loop->last), @endif @endforeach.
        The stable key is fixed once created. Options in use cannot be deleted — deactivate them instead.</p>

    <div class="card mb-3"><div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light"><tr><th>Order</th><th>Label / description</th><th>Key</th><th>Color</th>@if ($group->supportsBehavior())<th>Behavior</th>@endif<th>Where used</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($options as $option)
                @php $formId = 'opt-'.$option->id; $count = array_sum(array_column($usage[$option->id], 'count')); @endphp
                <tr @class(['table-secondary' => ! $option->is_active])>
                    <td class="text-nowrap">
                        <form method="POST" action="{{ route('admin.options.move', [$option, 'up']) }}" class="d-inline">@csrf<button class="btn btn-sm btn-link p-0" title="Move up" @disabled($loop->first)><i class="bi bi-arrow-up"></i></button></form>
                        <form method="POST" action="{{ route('admin.options.move', [$option, 'down']) }}" class="d-inline">@csrf<button class="btn btn-sm btn-link p-0" title="Move down" @disabled($loop->last)><i class="bi bi-arrow-down"></i></button></form>
                    </td>
                    <td>
                        <input form="{{ $formId }}" name="label" value="{{ $option->label }}" class="form-control form-control-sm mb-1" required maxlength="120" aria-label="Label">
                        <input form="{{ $formId }}" name="description" value="{{ $option->description }}" class="form-control form-control-sm" maxlength="500" placeholder="Description" aria-label="Description">
                    </td>
                    <td><code class="small">{{ $option->value }}</code></td>
                    <td>
                        <select form="{{ $formId }}" name="color" class="form-select form-select-sm" aria-label="Color">
                            <option value="">—</option>
                            @foreach (OptionCatalog::COLORS as $color)<option value="{{ $color }}" @selected($option->color === $color)>{{ $color }}</option>@endforeach
                        </select>
                        <x-option-badge :option="$option" class="mt-1" />
                    </td>
                    @if ($group->supportsBehavior())
                        <td>
                            <select form="{{ $formId }}" name="behavior" class="form-select form-select-sm" aria-label="Behavior">
                                @foreach (OptionCatalog::TASK_STATUS_BEHAVIORS as $value => $label)<option value="{{ $value }}" @selected($option->behavior === $value)>{{ ucfirst($value) }}</option>@endforeach
                            </select>
                        </td>
                    @endif
                    <td class="small">
                        @foreach ($usage[$option->id] as $u)
                            @if ($u['count'] > 0)<div>{{ $u['label'] }}: {{ $u['count'] }}</div>@endif
                        @endforeach
                        @if ($count === 0)<span class="text-muted">Unused</span>@endif
                    </td>
                    <td>@if ($option->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif</td>
                    <td class="text-nowrap text-end">
                        <form id="{{ $formId }}" method="POST" action="{{ route('admin.options.update', $option) }}" class="d-inline">@csrf @method('PUT')<button class="btn btn-sm btn-outline-primary">Save</button></form>
                        <form method="POST" action="{{ route('admin.options.toggle', $option) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $option->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                        @if ($count === 0)
                            <form method="POST" action="{{ route('admin.options.destroy', $option) }}" class="d-inline" data-confirm="Delete “{{ $option->label }}”?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>

    <div class="card">
        <div class="card-header">Add option</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.options.store', $group) }}">
                @csrf
                <div class="row g-2 align-items-end">
                    <x-form.input name="label" label="Label" required maxlength="120" col="col-md-3" />
                    <x-form.input name="value" label="Stable key (optional)" help="Lowercase, digits and _. Cannot be changed later." maxlength="60" col="col-md-3" />
                    <x-form.input name="description" label="Description" maxlength="500" col="col-md-3" />
                    <div class="col-md-1">
                        <label class="form-label" for="new_color">Color</label>
                        <select id="new_color" name="color" class="form-select"><option value="">—</option>@foreach (OptionCatalog::COLORS as $color)<option value="{{ $color }}">{{ $color }}</option>@endforeach</select>
                    </div>
                    @if ($group->supportsBehavior())
                        <div class="col-md-2">
                            <label class="form-label" for="new_behavior">Behavior</label>
                            <select id="new_behavior" name="behavior" class="form-select">@foreach (OptionCatalog::TASK_STATUS_BEHAVIORS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                        </div>
                    @endif
                </div>
                <button class="btn btn-primary btn-sm mt-3">Add</button>
            </form>
        </div>
    </div>
</x-layouts.app>
