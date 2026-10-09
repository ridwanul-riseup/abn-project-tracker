@php
    $name = $field['name'];
    $value = $record->getAttribute($name);
    $related = isset($field['relation']) ? $record->getRelationValue($field['relation']) : null;
@endphp
@switch($field['type'])
    @case('option')
        <x-option-badge :option="$related" />
        @break
    @case('product')
    @case('application')
    @case('repository')
    @case('document')
        <x-ref :model="$related" :name="$field['type'] !== 'repository'" />
        @break
    @case('environment')
        @if ($related)<a href="{{ route('registries.show', ['environments', $related->id]) }}">{{ $related->name }}</a>@else<span class="text-muted">—</span>@endif
        @break
    @case('repositories')
        @forelse ($related ?? [] as $repo)<x-ref :model="$repo" />@if (! $loop->last), @endif @empty <span class="text-muted">—</span> @endforelse
        @break
    @case('url')
        <x-ext-link :url="$value" :label="$compact ?? false ? 'Open' : null" :copy="! ($compact ?? false)" />
        @break
    @case('date')
        {{ $value?->format('Y-m-d') ?? '—' }}
        @break
    @case('textarea')
        @if ($value){!! nl2br(e($compact ?? false ? \Illuminate\Support\Str::limit($value, 120) : $value)) !!}@else<span class="text-muted">—</span>@endif
        @break
    @default
        @if ($value === null || $value === '')
            <span class="text-muted">—</span>
        @elseif (! empty($field['mono']))
            <code>{{ $value }}</code>
        @else
            {{ $value }}
        @endif
@endswitch
