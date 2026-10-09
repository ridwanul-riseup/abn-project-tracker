@props(['title', 'subtitle' => null])
<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">{{ $title }}</h1>
        @if ($subtitle)
            <div class="text-muted small mt-1">{{ $subtitle }}</div>
        @endif
    </div>
    @isset($actions)
        <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
