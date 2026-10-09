<x-layouts.app :title="$task->key" :breadcrumbs="['Tasks' => route('tasks.index'), $task->key => null]">
    <x-page-header :title="$task->key.' · '.$task->title">
        <x-slot:actions>
            @can('update', $task)
                <a href="{{ route('tasks.edit', $task) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($task->status?->behavior === 'blocked')
        <div class="alert alert-danger py-2"><i class="bi bi-slash-circle"></i> <strong>Blocked:</strong> {{ $task->blocker_description }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3"><div class="card-body">
                <h2 class="h6 text-muted">Description</h2>
                @if ($task->description)<div class="text-pre-wrap">{{ $task->description }}</div>@else<p class="text-muted mb-0">No description.</p>@endif
                @if ($task->completion_notes)
                    <h2 class="h6 text-muted mt-3">Completion notes</h2>
                    <div class="text-pre-wrap">{{ $task->completion_notes }}</div>
                @endif
            </div></div>

            @can('updateProgress', $task)
                <div class="card mb-3">
                    <div class="card-header">Update progress</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('tasks.progress', $task) }}">
                            @csrf @method('PATCH')
                            <div class="row">
                                <x-form.option-select group="task_status" name="status_id" label="Status" :selected="$task->status_id" required :placeholder="false" col="col-md-4 mb-3" />
                                <x-form.textarea name="blocker_description" label="Blocker (required when blocked)" :value="$task->blocker_description" rows="2" col="col-md-8 mb-3" />
                                <x-form.textarea name="completion_notes" label="Completion notes" :value="$task->completion_notes" rows="2" col="col-12 mb-3" />
                            </div>
                            <button class="btn btn-outline-primary btn-sm">Save progress</button>
                        </form>
                    </div>
                </div>
            @endcan

            <div class="card" id="comments">
                <div class="card-header">Comments ({{ $comments->total() }})</div>
                <ul class="list-group list-group-flush">
                    @forelse ($comments as $comment)
                        <li class="list-group-item">
                            <div class="small text-muted mb-1"><strong class="text-body">{{ $comment->user?->name ?? 'Deleted user' }}</strong> · {{ $comment->created_at?->format('Y-m-d H:i') }}</div>
                            <div class="text-pre-wrap">{{ $comment->body }}</div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">No comments yet.</li>
                    @endforelse
                </ul>
                <x-list-pager :items="$comments" />
                @can('comment', $task)
                    <div class="card-body border-top">
                        <form method="POST" action="{{ route('tasks.comments.store', $task) }}">
                            @csrf
                            <x-form.textarea name="body" label="Add a comment" rows="3" required />
                            <button class="btn btn-primary btn-sm">Comment</button>
                        </form>
                    </div>
                @endcan
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card small"><div class="card-body">
                <dl class="mb-0">
                    <dt class="text-muted">Status</dt><dd><x-option-badge :option="$task->status" /></dd>
                    <dt class="text-muted">Type</dt><dd><x-option-badge :option="$task->type" /></dd>
                    <dt class="text-muted">Priority</dt><dd><x-option-badge :option="$task->priority" /></dd>
                    <dt class="text-muted">Assignee</dt><dd>{{ $task->assignee?->name ?? 'Unassigned' }}</dd>
                    <dt class="text-muted">Reporter</dt><dd>{{ $task->reporter?->name ?? '—' }}</dd>
                    <dt class="text-muted">Product</dt><dd><x-ref :model="$task->product" :name="true" /></dd>
                    @if ($task->application)<dt class="text-muted">Application</dt><dd><x-ref :model="$task->application" :name="true" /></dd>@endif
                    @if ($task->repository)<dt class="text-muted">Repository</dt><dd><x-ref :model="$task->repository" /></dd>@endif
                    @if ($task->module)<dt class="text-muted">Module</dt><dd><x-ref :model="$task->module" :name="true" /></dd>@endif
                    <dt class="text-muted">Start / due</dt>
                    <dd>{{ $task->start_date?->format('Y-m-d') ?? '—' }} / <span class="@if ($task->isOverdue()) text-danger fw-semibold @endif">{{ $task->due_date?->format('Y-m-d') ?? '—' }}</span></dd>
                    <dt class="text-muted">Issue</dt><dd><x-ext-link :url="$task->issue_url" /></dd>
                    <dt class="text-muted">Updated</dt><dd>{{ $task->updated_at?->format('Y-m-d H:i') }}</dd>
                </dl>
            </div></div>
            <div class="card small mt-3" id="documents">
                <div class="card-header">Linked documents</div>
                <ul class="list-group list-group-flush">
                    @forelse ($documents as $doc)
                        <li class="list-group-item"><a href="{{ route('docs.show', $doc) }}">{{ $doc->title }}</a></li>
                    @empty
                        <li class="list-group-item text-muted">None.</li>
                    @endforelse
                    @if ($hiddenDocuments > 0)
                        <li class="list-group-item text-muted fst-italic"><i class="bi bi-lock"></i> {{ $hiddenDocuments }} restricted {{ \Illuminate\Support\Str::plural('document', $hiddenDocuments) }}</li>
                    @endif
                </ul>
                <x-list-pager :items="$documents" />
            </div>
        </div>
    </div>
</x-layouts.app>
