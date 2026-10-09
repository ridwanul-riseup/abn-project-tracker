@php
    $editing = $dependency->exists;
    $readOnly = $readOnly ?? false;
    $selectedTarget = old('target', $editing ? $dependency->target_type.':'.$dependency->target_id : '');
    $selectedSource = old('source', $preselectedSource);
@endphp
<x-layouts.app :title="$editing ? $dependency->code : 'New dependency'"
               :breadcrumbs="['Dependencies' => route('dependencies.index'), ($editing ? $dependency->code : 'New') => null]">
    <x-page-header :title="$editing ? 'Dependency '.$dependency->code : 'New dependency'" subtitle="Source depends on target." >
        <x-slot:actions>
            @if ($editing && ! $readOnly)
                <form method="POST" action="{{ route('dependencies.destroy', $dependency) }}" data-confirm="Delete dependency {{ $dependency->code }}?">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Delete</button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('dependencies.update', $dependency) : route('dependencies.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif
        <fieldset @disabled($readOnly)>
            <div class="card mb-3"><div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="source">Source component <span class="text-danger">*</span></label>
                        <select class="form-select @error('source') is-invalid @enderror" name="source" id="source" required>
                            <option value="">— Select —</option>
                            @foreach ($components as $group => $items)
                                <optgroup label="{{ $group }}">
                                    @foreach ($items as $value => $label)<option value="{{ $value }}" @selected($selectedSource === $value)>{{ $label }}</option>@endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('source')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="target">Depends on (target) <span class="text-danger">*</span></label>
                        <select class="form-select @error('target') is-invalid @enderror" name="target" id="target" required>
                            <option value="">— Select —</option>
                            @foreach ($components as $group => $items)
                                <optgroup label="{{ $group }}">
                                    @foreach ($items as $value => $label)<option value="{{ $value }}" @selected($selectedTarget === $value)>{{ $label }}</option>@endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('target')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row">
                    <x-form.option-select group="dependency_type" name="dependency_type_id" label="Dependency type" :selected="$dependency->dependency_type_id" :use-default="! $dependency->exists" col="col-md-4 mb-3" />
                    <x-form.option-select group="criticality" name="criticality_id" label="Criticality" :selected="$dependency->criticality_id" :use-default="! $dependency->exists" col="col-md-4 mb-3" />
                    <x-form.option-select group="dependency_status" name="status_id" label="Status" :selected="$dependency->status_id" :use-default="! $dependency->exists" col="col-md-4 mb-3" />
                </div>
                <x-form.textarea name="description" label="Description" :value="$dependency->description" />
                <x-form.textarea name="failure_impact" label="Failure impact" :value="$dependency->failure_impact" help="What breaks if the target is unavailable?" />
                <x-form.select name="document_id" label="Related documentation" :options="$documents" :selected="$dependency->document_id" />
                <x-form.textarea name="notes" label="Notes" :value="$dependency->notes" />
            </div></div>
            @unless ($readOnly)
                <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Record dependency' }}</button>
            @endunless
            <a href="{{ route('dependencies.index') }}" class="btn btn-link">Back to list</a>
        </fieldset>
    </form>
    @if ($editing && $dependency->document)
        <p class="small mt-3">Related documentation: <x-ref :model="$dependency->document" /></p>
    @endif
</x-layouts.app>
