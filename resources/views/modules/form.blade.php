@php $editing = $module->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$module->code : 'New module'"
               :breadcrumbs="['Modules' => route('modules.index')] + ($editing ? [$module->code => route('modules.show', $module)] : []) + [($editing ? 'Edit' : 'New') => null]">
    <x-page-header :title="$editing ? 'Edit module '.$module->code : 'New module'" />
    <form method="POST" action="{{ $editing ? route('modules.update', $module) : route('modules.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card mb-3"><div class="card-body">
            <div class="row">
                <x-form.input name="code" label="Module ID" :value="$module->code" required col="col-md-3 mb-3" help="e.g. OTT-SUB" />
                <x-form.input name="name" label="Module name" :value="$module->name" required col="col-md-5 mb-3" />
                <x-form.option-select group="module_status" name="status_id" label="Status" :selected="$module->status_id" :use-default="! $module->exists" col="col-md-2 mb-3" />
                <x-form.option-select group="priority" name="priority_id" label="Priority" :selected="$module->priority_id" :use-default="! $module->exists" col="col-md-2 mb-3" />
            </div>
            <div class="row">
                <x-form.select name="product_id" label="Product" :options="$products" :selected="$module->product_id" required col="col-md-4 mb-3" placeholder="— Select product —" />
                <x-form.select name="application_id" label="Application" :options="$applications" :selected="$module->application_id" col="col-md-4 mb-3" />
                <x-form.input name="owner" label="Module owner" :value="$module->owner" col="col-md-4 mb-3" />
            </div>
            <x-form.textarea name="description" label="Description" :value="$module->description" />
            <div class="row">
                <x-form.select name="primary_repository_id" label="Primary repository" :options="$repositories" :selected="$module->primary_repository_id" col="col-md-4 mb-3" />
                <x-form.select name="related_repository_ids[]" label="Related repositories" :options="$repositories" :selected="$selectedRelated" :multiple="true" col="col-md-4 mb-3" />
                <x-form.select name="database_reference_id" label="Database" :options="$databases" :selected="$module->database_reference_id" col="col-md-4 mb-3" />
            </div>
            <div class="row">
                <x-form.input name="api_reference" label="API reference" :value="$module->api_reference" col="col-md-6 mb-3" />
                <x-form.input name="figma_url" label="Figma URL" type="url" :value="$module->figma_url" col="col-md-6 mb-3" />
            </div>
            <x-form.textarea name="notes" label="Notes" :value="$module->notes" />
        </div></div>
        <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create module' }}</button>
        <a href="{{ $editing ? route('modules.show', $module) : route('modules.index') }}" class="btn btn-link">Cancel</a>
    </form>
</x-layouts.app>
