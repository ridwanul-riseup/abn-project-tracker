@props(['url' => null, 'label' => null, 'copy' => false])
@if ($url && preg_match('#\Ahttps?://#i', $url))
    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="text-break-all">{{ $label ?? $url }} <i class="bi bi-box-arrow-up-right small"></i></a>
    @if ($copy)
        <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-copy="{{ $url }}" title="Copy link"><i class="bi bi-clipboard"></i></button>
    @endif
@elseif ($url)
    <span class="text-break-all">{{ $url }}</span>
@else
    <span class="text-muted">—</span>
@endif
