@props(['model' => null, 'name' => false])
@if ($model === null)
    <span class="text-muted">—</span>
@elseif (! $model instanceof \App\Support\Access\HasAccessScope || app(\App\Support\Access\AccessService::class)->canRead(auth()->user(), $model))
    @php $url = \App\Support\Links::url($model); @endphp
    @if ($url)
        <a href="{{ $url }}" {{ $attributes }}>{{ \App\Support\Links::label($model, $name) }}</a>
    @else
        <span {{ $attributes }}>{{ \App\Support\Links::label($model, $name) }}</span>
    @endif
@else
    <span class="text-muted fst-italic" title="You do not have access to this record"><i class="bi bi-lock"></i> Restricted</span>
@endif
