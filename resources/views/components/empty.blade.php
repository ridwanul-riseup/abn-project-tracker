@props(['message' => 'Nothing to show yet.', 'icon' => 'bi-inbox'])
<div class="text-center text-muted py-4">
    <i class="bi {{ $icon }} fs-3 d-block mb-1"></i>
    <div>{{ $message }}</div>
    {{ $slot }}
</div>
