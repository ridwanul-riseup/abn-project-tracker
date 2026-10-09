@php $editing = $application->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$application->code : 'New application'"
               :breadcrumbs="['Applications' => route('applications.index')] + ($editing ? [$application->code => route('applications.show', $application)] : []) + [($editing ? 'Edit' : 'New') => null]">
    <x-page-header :title="$editing ? 'Edit application '.$application->code : 'New application'" />
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ $editing ? route('applications.update', $application) : route('applications.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif
            <div class="row">
                <x-form.input name="code" label="Application ID" :value="$application->code" required col="col-md-3 mb-3" help="e.g. OTT-ADM" />
                <x-form.select name="product_id" label="Product" :options="$products" :selected="$application->product_id" required col="col-md-4 mb-3" placeholder="— Select product —" />
                <x-form.input name="name" label="Application name" :value="$application->name" required col="col-md-5 mb-3" />
            </div>
            <div class="row">
                <x-form.option-select group="application_type" name="application_type_id" label="Application type" :selected="$application->application_type_id" col="col-md-4 mb-3" />
                <x-form.option-select group="application_classification" name="classification_id" label="Frontend/backend" :selected="$application->classification_id" col="col-md-4 mb-3" />
                <x-form.option-select group="application_status" name="status_id" label="Status" :selected="$application->status_id" col="col-md-4 mb-3" />
            </div>
            <x-form.textarea name="description" label="Description" :value="$application->description" />
            <x-form.textarea name="notes" label="Notes" :value="$application->notes" />
            <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create application' }}</button>
            <a href="{{ $editing ? route('applications.show', $application) : route('applications.index') }}" class="btn btn-link">Cancel</a>
        </form>
    </div></div>
</x-layouts.app>
