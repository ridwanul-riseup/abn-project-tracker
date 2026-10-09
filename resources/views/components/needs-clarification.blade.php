@props(['items' => collect()])
@if ($items->isNotEmpty())
    <div class="alert alert-warning py-2">
        <div class="fw-semibold mb-1"><i class="bi bi-question-circle"></i> Needs Clarification ({{ $items->count() }})</div>
        <ul class="mb-0 small">
            @foreach ($items as $item)
                <li>
                    @if ($item->field)<code>{{ $item->field }}</code>: @endif{{ $item->issue }}
                    @if ($item->original_value)<span class="text-muted">— original: “{{ $item->original_value }}”</span>@endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
