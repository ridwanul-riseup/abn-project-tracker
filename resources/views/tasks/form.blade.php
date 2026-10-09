@php $editing = $task->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$task->key : 'New task'"
               :breadcrumbs="['Tasks' => route('tasks.index')] + ($editing ? [$task->key => route('tasks.show', $task), 'Edit' => null] : ['New' => null])">
    <x-page-header :title="$editing ? 'Edit task '.$task->key : 'New task'" />
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ $editing ? route('tasks.update', $task) : route('tasks.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif
            <div class="row">
                <x-form.input name="title" label="Title" :value="$task->title" required maxlength="200" col="col-md-8 mb-3" />
                @if ($editing)
                    <div class="col-md-4 mb-3"><label class="form-label">Product</label><div class="form-control-plaintext"><x-ref :model="$task->product" :name="true" /> <span class="small text-muted">(fixed — determines the key)</span></div></div>
                @else
                    <x-form.select name="product_id" label="Product" :options="$products" :selected="$task->product_id" required col="col-md-4 mb-3" />
                @endif
                <x-form.textarea name="description" label="Description" :value="$task->description" rows="5" col="col-12 mb-3" />
                <x-form.select name="application_id" label="Application" :options="$applications" :selected="$task->application_id" col="col-md-4 mb-3" />
                <x-form.select name="repository_id" label="Repository" :options="$repositories" :selected="$task->repository_id" col="col-md-4 mb-3" />
                <x-form.select name="module_id" label="Module" :options="$modules" :selected="$task->module_id" col="col-md-4 mb-3" />
                <x-form.option-select group="task_type" name="type_id" label="Type" :selected="$task->type_id" required :placeholder="false" col="col-md-4 mb-3" />
                <x-form.option-select group="task_status" name="status_id" label="Status" :selected="$task->status_id" :required="$editing" :placeholder="$editing ? false : '— Default (first open status) —'" col="col-md-4 mb-3" />
                <x-form.option-select group="priority" name="priority_id" label="Priority" :selected="$task->priority_id" required :placeholder="false" col="col-md-4 mb-3" />
                @if ($canAssign)
                    <x-form.select name="assignee_id" label="Assignee" :options="$assignees" :selected="$task->assignee_id" placeholder="— Unassigned —" help="Must have access to the task's product, repository or module." col="col-md-4 mb-3" />
                @endif
                <x-form.input name="start_date" label="Start date" type="date" :value="$task->start_date" col="col-md-4 mb-3" />
                <x-form.input name="due_date" label="Due date" type="date" :value="$task->due_date" col="col-md-4 mb-3" />
                <x-form.input name="issue_url" label="External issue link" type="url" :value="$task->issue_url" col="col-md-8 mb-3" />
                <x-form.select name="document_ids[]" label="Linked documents" :options="$documents" :selected="$selectedDocuments" :multiple="true" help="Ctrl/Cmd-click to select several." col="col-md-4 mb-3" />
                <x-form.textarea name="blocker_description" label="Blocker description" :value="$task->blocker_description" rows="2" help="Required when the status is a blocked status." col="col-md-6 mb-3" />
                <x-form.textarea name="completion_notes" label="Completion notes" :value="$task->completion_notes" rows="2" col="col-md-6 mb-3" />
            </div>
            <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create task' }}</button>
            <a href="{{ $editing ? route('tasks.show', $task) : route('tasks.index') }}" class="btn btn-link">Cancel</a>
        </form>
    </div></div>
</x-layouts.app>
