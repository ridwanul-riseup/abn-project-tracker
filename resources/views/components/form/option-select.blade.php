@props(['group', 'name', 'label', 'selected' => null, 'required' => false, 'placeholder' => '— None —', 'help' => null, 'col' => null])
@php
    $choices = app(\App\Support\Options\Options::class)->for($group, $selected, old($name))
        ->mapWithKeys(fn ($o) => [$o->id => $o->label.($o->is_active ? '' : ' (inactive)')]);
@endphp
<x-form.select :name="$name" :label="$label" :options="$choices" :selected="$selected" :required="$required"
               :placeholder="$placeholder" :help="$help" :col="$col" {{ $attributes }} />
