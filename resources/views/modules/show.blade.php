<x-layouts.app :title="$module->code" :breadcrumbs="['Modules' => route('modules.index'), $module->code => null]">
    <x-page-header :title="$module->code.' — '.$module->name" :subtitle="$module->description">
        <x-slot:actions>
            @can('docs.create-in', $module)
                <a href="{{ route('docs.create', ['product_id' => $module->product_id, 'module_id' => $module->id, 'template' => 'module']) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-journal-plus"></i> New document</a>
            @endcan
            @can('tasks.create-in', $module)
                <a href="{{ route('tasks.create', ['product_id' => $module->product_id, 'module_id' => $module->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-square"></i> New task</a>
            @endcan
            @can('update', $module)
                <a href="{{ route('modules.edit', $module) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                @if ($module->isArchived())
                    <form method="POST" action="{{ route('modules.restore', $module) }}">@csrf<button class="btn btn-outline-success btn-sm">Restore</button></form>
                @else
                    <form method="POST" action="{{ route('modules.archive', $module) }}" data-confirm="Archive this module?">@csrf<button class="btn btn-outline-danger btn-sm">Archive</button></form>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>
    <x-needs-clarification :items="$clarifications" />

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-3"><div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5">Product</dt><dd class="col-7"><x-ref :model="$module->product" :name="true" /></dd>
                    <dt class="col-5">Application</dt><dd class="col-7"><x-ref :model="$module->application" :name="true" /></dd>
                    <dt class="col-5">Status</dt><dd class="col-7"><x-option-badge :option="$module->status" /></dd>
                    <dt class="col-5">Priority</dt><dd class="col-7"><x-option-badge :option="$module->priority" /></dd>
                    <dt class="col-5">Owner</dt><dd class="col-7">{{ $module->owner ?? '—' }}</dd>
                    <dt class="col-5">Primary repository</dt><dd class="col-7"><x-ref :model="$module->primaryRepository" :name="true" /></dd>
                    <dt class="col-5">Related repositories</dt>
                    <dd class="col-7">@forelse ($relatedRepositories as $r)<x-ref :model="$r" />@if (! $loop->last), @endif @empty — @endforelse</dd>
                    <dt class="col-5">API reference</dt><dd class="col-7"><code>{{ $module->api_reference ?? '—' }}</code></dd>
                    <dt class="col-5">Database</dt><dd class="col-7"><x-ref :model="$module->databaseReference" :name="true" /></dd>
                    <dt class="col-5">Figma</dt><dd class="col-7"><x-ext-link :url="$module->figma_url" label="Open in Figma" /></dd>
                </dl>
                @if ($module->notes)<hr><div class="small text-muted">{!! nl2br(e($module->notes)) !!}</div>@endif
            </div></div>
            <div class="card">
                <div class="card-header">Technical documentation</div>
                <ul class="list-group list-group-flush small">
                    @forelse ($documents as $doc)
                        <li class="list-group-item"><a href="{{ route('docs.show', $doc) }}">{{ $doc->title }}</a> <x-option-badge :option="$doc->category" /></li>
                    @empty
                        <li class="list-group-item text-muted">No documentation yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">Recent tasks</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <tbody>
                    @forelse ($tasks as $task)
                        <tr><td><a href="{{ route('tasks.show', $task) }}">{{ $task->key }}</a></td><td>{{ $task->title }}</td><td><x-option-badge :option="$task->status" /></td><td><x-option-badge :option="$task->priority" /></td><td class="small">{{ $task->assignee?->name ?? 'Unassigned' }}</td></tr>
                    @empty
                        <tr><td class="text-muted small">No tasks.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
            <div class="card">
                <div class="card-header">Dependencies</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <tbody>
                    @forelse ($dependencies as $dep)
                        <tr><td><a href="{{ route('dependencies.edit', $dep) }}">{{ $dep->code }}</a></td><td><x-ref :model="$dep->source" /> <i class="bi bi-arrow-right"></i> <x-ref :model="$dep->target" /></td><td><x-option-badge :option="$dep->dependencyType" /></td><td><x-option-badge :option="$dep->criticality" /></td></tr>
                    @empty
                        <tr><td class="text-muted small">No dependencies recorded for this module.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
</x-layouts.app>
