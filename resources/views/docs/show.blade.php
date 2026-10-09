<x-layouts.app :title="$document->title" :breadcrumbs="['Documentation' => route('docs.index'), $document->title => null]">
    <x-page-header :title="$document->title" :subtitle="$document->summary">
        <x-slot:actions>
            <a href="{{ route('docs.history', $document) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history"></i> History ({{ $revisionCount }})</a>
            @if ($repoLinks)
                <a href="{{ $repoLinks['view'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm"><i class="bi bi-git"></i> View in Git</a>
            @endif
            @can('update', $document)
                <a href="{{ route('docs.edit', $document) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
            @endcan
            @can('archive', $document)
                @if ($document->isArchived())
                    <form method="POST" action="{{ route('docs.restore', $document) }}">@csrf<button class="btn btn-outline-success btn-sm">Restore</button></form>
                @else
                    <form method="POST" action="{{ route('docs.archive', $document) }}" data-confirm="Archive this document? The file is kept.">@csrf<button class="btn btn-outline-danger btn-sm">Archive</button></form>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($document->isArchived())<div class="alert alert-secondary py-2">This document is archived.</div>@endif
    @if ($fileMissing)
        <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> The Markdown file for this document is missing from the docs directory.</div>
    @elseif ($changedOutside)
        <div class="alert alert-info py-2"><i class="bi bi-info-circle"></i> The file was changed outside the hub (for example via Git) since its last recorded revision. The content below is the current file.</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-9">
            <div class="card"><div class="card-body markdown-body">
                @if ($html !== null){!! $html !!}@endif
            </div></div>
        </div>
        <div class="col-lg-3">
            <div class="card small"><div class="card-body">
                <dl class="mb-0">
                    <dt class="text-muted">Category</dt><dd><x-option-badge :option="$document->category" /></dd>
                    <dt class="text-muted">Product</dt><dd>@if ($document->product)<x-ref :model="$document->product" :name="true" />@else<span class="text-muted">Cross-product</span>@endif</dd>
                    @if ($document->repository)<dt class="text-muted">Repository</dt><dd><x-ref :model="$document->repository" /></dd>@endif
                    @if ($document->module)<dt class="text-muted">Module</dt><dd><x-ref :model="$document->module" :name="true" /></dd>@endif
                    <dt class="text-muted">Visibility</dt><dd>{{ \App\Models\Document::VISIBILITIES[$document->visibility] ?? $document->visibility }}</dd>
                    <dt class="text-muted">Path</dt><dd><code class="text-break-all">{{ $document->path }}</code></dd>
                    <dt class="text-muted">Owner</dt><dd>{{ $document->owner?->name ?? '—' }}</dd>
                    <dt class="text-muted">Created by</dt><dd>{{ $document->creator?->name ?? 'Imported' }}</dd>
                    <dt class="text-muted">Last updated</dt><dd>{{ $document->content_updated_at?->format('Y-m-d H:i') }} by {{ $document->editor?->name ?? 'import' }}</dd>
                </dl>
            </div></div>
            @if ($tasks->isNotEmpty())
                <div class="card small mt-3" id="tasks">
                    <div class="card-header">Linked tasks</div>
                    <ul class="list-group list-group-flush">
                        @foreach ($tasks as $task)
                            <li class="list-group-item"><a href="{{ route('tasks.show', $task) }}">{{ $task->key }}</a> {{ $task->title }} <x-option-badge :option="$task->status" /></li>
                        @endforeach
                    </ul>
                    <x-list-pager :items="$tasks" />
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
