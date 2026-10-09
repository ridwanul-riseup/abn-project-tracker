@php
    $subject = $log->subject_type && $log->subject_id ? $log->subjectModel() : null;
    $url = $subject ? \App\Support\Links::url($subject) : null;
@endphp
<strong>{{ $log->user?->name ?? 'System' }}</strong>
<span class="text-muted">{{ str_replace(['.', '_'], ' ', $log->action) }}</span>
@if ($url)
    <a href="{{ $url }}">{{ $log->subject_label }}</a>
@else
    {{ $log->subject_label }}
@endif
<div class="text-muted">{{ $log->created_at?->diffForHumans() }}</div>
