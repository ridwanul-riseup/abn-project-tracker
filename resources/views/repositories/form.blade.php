@php $editing = $repository->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$repository->code : 'Register repository'"
               :breadcrumbs="['Repositories' => route('repositories.index')] + ($editing ? [$repository->code => route('repositories.show', $repository)] : []) + [($editing ? 'Edit' : 'New') => null]">
    <x-page-header :title="$editing ? 'Edit repository '.$repository->code : 'Register repository'" />
    <form method="POST" action="{{ $editing ? route('repositories.update', $repository) : route('repositories.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card mb-3"><div class="card-header">Identity</div><div class="card-body">
            <div class="row">
                <x-form.input name="code" label="Repository ID" :value="$repository->code" required col="col-md-3 mb-3" help="e.g. OTT-WEB-BE" />
                <x-form.input name="name" label="Repository name" :value="$repository->name" required col="col-md-5 mb-3" />
                <x-form.option-select group="repository_layer" name="layer_id" label="Layer" :selected="$repository->layer_id" :use-default="! $repository->exists" col="col-md-2 mb-3" />
                <x-form.option-select group="repository_status" name="status_id" label="Status" :selected="$repository->status_id" :use-default="! $repository->exists" col="col-md-2 mb-3" />
            </div>
            <div class="row">
                <x-form.select name="product_id" label="Product" :options="$products" :selected="$repository->product_id" required col="col-md-4 mb-3" placeholder="— Select product —" />
                <x-form.select name="application_id" label="Application" :options="$applications" :selected="$repository->application_id" col="col-md-4 mb-3" help="Must belong to the selected product." />
                <x-form.input name="url" label="Repository URL (optional)" type="url" :value="$repository->url" col="col-md-4 mb-3" help="Leave empty if only the name is known." />
            </div>
        </div></div>

        <div class="card mb-3"><div class="card-header">Technical details</div><div class="card-body">
            <div class="row">
                <x-form.input name="primary_owner" label="Primary owner" :value="$repository->primary_owner" col="col-md-4 mb-3" />
                <x-form.input name="tech_stack" label="Technology stack" :value="$repository->tech_stack" col="col-md-4 mb-3" />
                <x-form.input name="module_label" label="Primary module / functional area" :value="$repository->module_label" col="col-md-4 mb-3" />
            </div>
            <div class="row">
                <x-form.select name="database_reference_id" label="Database" :options="$databases" :selected="$repository->database_reference_id" col="col-md-4 mb-3" />
                <x-form.input name="api_reference" label="API base path / reference" :value="$repository->api_reference" col="col-md-4 mb-3" help="e.g. /api/v1/ott/*" />
                <x-form.input name="current_branch" label="Current branch" :value="$repository->current_branch" col="col-md-4 mb-3" />
            </div>
            <div class="row">
                <x-form.input name="figma_url" label="Figma URL" type="url" :value="$repository->figma_url" col="col-md-4 mb-3" />
                <x-form.input name="issue_tracker_url" label="Issue tracker URL" type="url" :value="$repository->issue_tracker_url" col="col-md-4 mb-3" />
                <x-form.input name="issue_tracker_ref" label="Issue key / pattern" :value="$repository->issue_tracker_ref" col="col-md-4 mb-3" help="e.g. OTT-ADM-*" />
            </div>
            <div class="row">
                <x-form.option-select group="deployment_target" name="deployment_target_id" label="Deployment target" :selected="$repository->deployment_target_id" :use-default="! $repository->exists" col="col-md-4 mb-3" />
                <x-form.select name="environment_ids[]" label="Environments" :options="$environments" :selected="$repository->exists ? $repository->environments->pluck('id') : []" :multiple="true" col="col-md-4 mb-3" help="Ctrl/Cmd-click to select several." />
            </div>
        </div></div>

        <div class="card mb-3"><div class="card-header">Notes</div><div class="card-body">
            <x-form.textarea name="notes" label="Notes" :value="$repository->notes" rows="4" help="Visible to everyone who can read this repository. Never store credentials." />
            @if ($canSeeSensitive)
                <x-form.textarea name="sensitive_notes" label="Sensitive technical notes" :value="$repository->sensitive_notes" rows="4"
                                 help="Only visible to users with the 'View sensitive technical notes' capability. Still no secrets — reference the secret manager instead." />
            @endif
        </div></div>

        <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Register repository' }}</button>
        <a href="{{ $editing ? route('repositories.show', $repository) : route('repositories.index') }}" class="btn btn-link">Cancel</a>
    </form>
</x-layouts.app>
