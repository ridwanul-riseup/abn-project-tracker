@props(['items'])
@if ($items->hasPages())
    <div class="card-footer py-2 small d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="text-muted">{{ $items->firstItem() }}–{{ $items->lastItem() }} of {{ $items->total() }}</span>
        {{ $items->onEachSide(1)->links('pagination::simple-bootstrap-5') }}
    </div>
@endif
