@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'help' => null, 'col' => null])
@php $id = 'f_'.str_replace(['[', ']', '.'], '_', $name); @endphp
<div class="{{ $col ?? 'mb-3' }}">
    <label for="{{ $id }}" class="form-label">{{ $label }}@if ($required) <span class="text-danger">*</span>@endif</label>
    <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}"
           value="{{ $type === 'password' ? '' : old($name, $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value) }}"
           @if ($required) required @endif
           {{ $attributes->merge(['class' => 'form-control'.($errors->has($name) ? ' is-invalid' : '')]) }}>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
