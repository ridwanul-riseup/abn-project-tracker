<x-layouts.app :title="'History: '.$document->title" :breadcrumbs="['Documentation' => route('docs.index'), $document->title => route('docs.show', $document), 'History' => null]">
    <x-page-header :title="'Revision history'" :subtitle="$document->title.' — '.$document->path">
        <x-slot:actions>
            @if ($repoLinks)
                <a href="{{ $repoLinks['history'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm"><i class="bi bi-git"></i> Git history</a>
            @endif
        </x-slot:actions>
    </x-page-header>
    <p class="small text-muted">The hub records who changed what and when. Full content diffs live in Git when the docs directory is version-controlled.</p>
    <div class="card"><div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>When</th><th>Who</th><th>Action</th><th>Summary</th><th>Size</th><th>Lines</th><th>Metadata changes</th></tr></thead>
            <tbody>
            @forelse ($revisions as $rev)
                <tr>
                    <td class="small text-nowrap">{{ $rev->created_at?->format('Y-m-d H:i') }}</td>
                    <td class="small">{{ $rev->user?->name ?? 'System' }}</td>
                    <td><span class="badge bg-light text-dark">{{ str_replace('_', ' ', $rev->action) }}</span></td>
                    <td class="small">{{ $rev->summary }}</td>
                    <td class="small text-nowrap">{{ number_format($rev->size_bytes) }} B</td>
                    <td class="small text-nowrap">{{ $rev->line_count }} <span class="text-muted">({{ $rev->lines_delta >= 0 ? '+' : '' }}{{ $rev->lines_delta }})</span></td>
                    <td class="small changes-cell">
                        @foreach ($rev->metadata_changes ?? [] as $key => $change)
                            <div><code>{{ $key }}</code>: {{ is_scalar($change['from'] ?? null) ? $change['from'] : '—' }} → {{ is_scalar($change['to'] ?? null) ? $change['to'] : '—' }}</div>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty message="No revisions recorded." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $revisions->links() }}</div>
</x-layouts.app>
