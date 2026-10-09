@php
    $opts = app(\App\Support\Options\Options::class);
    $withProduct ??= false;
    $withArchived ??= false;
    $withModuleStatus ??= false;
@endphp
<form class="card card-body mb-3 py-2" method="GET">
    <div class="row g-2 align-items-end">
        @if ($withProduct)
            <div class="col-md-3">
                <select name="product_id" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All products</option>
                    @foreach ($productChoices as $id => $label)<option value="{{ $id }}" @selected(request('product_id') == $id)>{{ $label }}</option>@endforeach
                </select>
            </div>
        @endif
        @if ($withModuleStatus)
            <div class="col-md-2">
                <select name="module_status_id" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All module statuses</option>
                    @foreach ($opts->forFilter('module_status') as $o)<option value="{{ $o->id }}" @selected(request('module_status_id') == $o->id)>{{ $o->filterLabel() }}</option>@endforeach
                </select>
            </div>
        @endif
        @foreach (['type_id' => ['task_type', 'All task types'], 'priority_id' => ['priority', 'All priorities']] as $name => [$group, $placeholder])
            <div class="col-md-2">
                <select name="{{ $name }}" class="form-select form-select-sm" data-autosubmit>
                    <option value="">{{ $placeholder }}</option>
                    @foreach ($opts->forFilter($group) as $o)<option value="{{ $o->id }}" @selected(request($name) == $o->id)>{{ $o->filterLabel() }}</option>@endforeach
                </select>
            </div>
        @endforeach
        <div class="col-md-2">
            <select name="assignee_id" class="form-select form-select-sm" data-autosubmit>
                <option value="">Any assignee</option>
                @foreach ($assignees as $id => $name)<option value="{{ $id }}" @selected(request('assignee_id') == $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
        @if ($withArchived)
            <div class="col-md-auto">
                <div class="form-check form-check-inline small mb-1">
                    <input class="form-check-input" type="checkbox" name="archived" value="1" id="reportArchived" @checked(request()->boolean('archived')) data-autosubmit>
                    <label class="form-check-label" for="reportArchived">Include archived</label>
                </div>
            </div>
        @endif
        <div class="col-md-auto"><button class="btn btn-sm btn-outline-secondary">Apply</button></div>
        @if (array_filter(request()->except('page')))
            <div class="col-md-auto"><a class="btn btn-sm btn-link" href="{{ url()->current() }}">Reset</a></div>
        @endif
    </div>
</form>
