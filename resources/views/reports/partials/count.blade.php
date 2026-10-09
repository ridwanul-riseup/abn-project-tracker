@if ($n === 0)<span class="text-body-tertiary">0</span>@elseif ($url)<a class="{{ $class ?? '' }}" href="{{ $url }}">{{ $n }}</a>@else<span class="{{ $class ?? '' }}">{{ $n }}</span>@endif
