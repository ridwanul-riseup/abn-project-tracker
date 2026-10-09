<x-layouts.app title="Document templates" :breadcrumbs="['Global settings' => route('admin.options.index'), 'Document templates' => null]">
    <x-page-header title="Document templates" :subtitle="'Starting content offered when someone creates a new document. Placeholders '.implode(', ', \App\Models\DocumentTemplate::PLACEHOLDERS).' are filled in automatically.'">
        <x-slot:actions>
            <a href="{{ route('admin.templates.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New template</a>
        </x-slot:actions>
    </x-page-header>

    @if ($templates->isEmpty())
        <x-empty message="No templates. New documents start with the title only." />
    @else
        <div class="card"><div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light"><tr><th>Order</th><th>Name</th><th>Key</th><th>Default category</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach ($templates as $template)
                    <tr @class(['table-secondary' => ! $template->is_active])>
                        <td class="text-nowrap">
                            <form method="POST" action="{{ route('admin.templates.move', [$template, 'up']) }}" class="d-inline">@csrf<button class="btn btn-sm btn-link p-0" title="Move up" @disabled($loop->first && $templates->onFirstPage())><i class="bi bi-arrow-up"></i></button></form>
                            <form method="POST" action="{{ route('admin.templates.move', [$template, 'down']) }}" class="d-inline">@csrf<button class="btn btn-sm btn-link p-0" title="Move down" @disabled($loop->last && ! $templates->hasMorePages())><i class="bi bi-arrow-down"></i></button></form>
                        </td>
                        <td><a class="fw-semibold" href="{{ route('admin.templates.edit', $template) }}">{{ $template->name }}</a>
                            @if ($template->description)<div class="small text-muted">{{ $template->description }}</div>@endif</td>
                        <td><code class="small">{{ $template->key }}</code></td>
                        <td>@if ($template->category)<x-option-badge :option="$template->category" />@else<span class="text-muted small">—</span>@endif</td>
                        <td>@if ($template->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif</td>
                        <td class="text-nowrap text-end">
                            <a href="{{ route('admin.templates.edit', $template) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="POST" action="{{ route('admin.templates.toggle', $template) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $template->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                            <form method="POST" action="{{ route('admin.templates.destroy', $template) }}" class="d-inline" data-confirm="Delete template “{{ $template->name }}”? Existing documents are not affected.">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button></form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div></div>
        <div class="mt-3">{{ $templates->links() }}</div>
    @endif
</x-layouts.app>
