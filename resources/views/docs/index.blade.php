<x-layouts.app title="Documentation" :breadcrumbs="['Documentation' => null]">
    <x-page-header title="Documentation" subtitle="Markdown files in the managed docs directory. Only documents you have access to are listed and searched.">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('docs.create', array_filter(['product_id' => request('product_id')])) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New document</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <form class="card card-body mb-3 py-2" method="GET">
        <div class="row g-2 align-items-end">
            <div class="col-md-4"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Search titles and content…"></div>
            <div class="col-md-3">
                <select name="category_id" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All categories</option>
                    @foreach (app(\App\Support\Options\Options::class)->forFilter('document_category') as $o)
                        <option value="{{ $o->id }}" @selected(request('category_id') == $o->id)>{{ $o->filterLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="product_id" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All products</option>
                    @foreach ($products as $id => $label)<option value="{{ $id }}" @selected(request('product_id') == $id)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-auto form-check ms-2">
                <input class="form-check-input" type="checkbox" name="archived" value="1" id="archived" @checked(request()->boolean('archived'))>
                <label class="form-check-label small" for="archived">Include archived</label>
            </div>
            <div class="col-md-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
        </div>
    </form>

    <div class="card"><div class="table-responsive">
        <table class="table table-hover table-sm mb-0 align-middle">
            <thead class="table-light"><tr><th>Title</th><th>Category</th><th>Associated with</th><th>Path</th><th>Last updated</th></tr></thead>
            <tbody>
            @forelse ($documents as $doc)
                <tr>
                    <td>
                        <a class="fw-semibold" href="{{ route('docs.show', $doc) }}">{{ $doc->title }}</a>
                        @if ($doc->isArchived())<span class="badge bg-secondary ms-1">Archived</span>@endif
                        @if ($doc->visibility === \App\Models\Document::VISIBILITY_ALL_USERS)<i class="bi bi-people text-muted ms-1" title="Visible to all users"></i>@endif
                        @if ($doc->summary)<div class="small text-muted">{{ \Illuminate\Support\Str::limit($doc->summary, 110) }}</div>@endif
                    </td>
                    <td><x-option-badge :option="$doc->category" /></td>
                    <td class="small">
                        @if ($doc->module)<x-ref :model="$doc->module" />@elseif ($doc->repository)<x-ref :model="$doc->repository" />@elseif ($doc->product)<x-ref :model="$doc->product" />@else<span class="text-muted">Cross-product</span>@endif
                    </td>
                    <td><code class="small">{{ $doc->path }}</code></td>
                    <td class="small text-nowrap">{{ $doc->content_updated_at?->format('Y-m-d') }}<div class="text-muted">{{ $doc->editor?->name ?? 'Imported' }}</div></td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty message="No documents match your filters." icon="bi-journal-text" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $documents->links() }}</div>
</x-layouts.app>
