@props(['option' => null])
@if ($option)
    @php
        $color = in_array($option->color, \App\Support\Options\OptionCatalog::COLORS, true) ? $option->color : 'secondary';
        $text = in_array($color, ['warning', 'info', 'light'], true) ? 'text-dark' : '';
    @endphp
    <span {{ $attributes->merge(['class' => "badge bg-{$color} {$text}"]) }} title="{{ $option->description }}">
        @if ($option->value === 'needs_clarification')<i class="bi bi-question-circle"></i>@endif
        {{ $option->label }}@if (! $option->is_active) <span class="opacity-75">(inactive)</span>@endif
    </span>
@else
    <span class="text-muted">—</span>
@endif
