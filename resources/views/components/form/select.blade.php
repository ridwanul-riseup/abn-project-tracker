@props(['name', 'label', 'options' => [], 'selected' => null, 'required' => false, 'placeholder' => '— None —', 'help' => null, 'col' => null, 'multiple' => false])
@php
    $key = str_replace('[]', '', $name);
    $id = 'f_'.str_replace(['[', ']', '.'], '_', $key);
    $current = old($key, $selected);
    $current = $multiple ? array_map('strval', (array) ($current instanceof \Illuminate\Support\Collection ? $current->all() : $current)) : (string) $current;
@endphp
<div class="{{ $col ?? 'mb-3' }}">
    <label for="{{ $id }}" class="form-label">{{ $label }}@if ($required) <span class="text-danger">*</span>@endif</label>
    <select id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif @if ($multiple) multiple size="6" @endif
            {{ $attributes->merge(['class' => 'form-select'.($errors->has($key) || $errors->has($key.'.*') ? ' is-invalid' : '')]) }}>
        @if (! $multiple && $placeholder !== false)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            @php $value = (string) $value; @endphp
            <option value="{{ $value }}" @selected($multiple ? in_array($value, $current, true) : $value === $current)>{{ $text }}</option>
        @endforeach
    </select>
    @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @error($key.'.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
