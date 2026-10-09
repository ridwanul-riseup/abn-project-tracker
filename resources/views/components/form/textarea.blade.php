@props(['name', 'label', 'value' => null, 'rows' => 3, 'required' => false, 'help' => null, 'col' => null])
@php $id = 'f_'.str_replace(['[', ']', '.'], '_', $name); @endphp
<div class="{{ $col ?? 'mb-3' }}">
    <label for="{{ $id }}" class="form-label">{{ $label }}@if ($required) <span class="text-danger">*</span>@endif</label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if ($required) required @endif
              {{ $attributes->merge(['class' => 'form-control'.($errors->has($name) ? ' is-invalid' : '')]) }}>{{ old($name, $value) }}</textarea>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
