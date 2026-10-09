@props(['group', 'name', 'label', 'selected' => null, 'required' => false, 'placeholder' => '— None —', 'help' => null, 'col' => null, 'useDefault' => false])
@php
    $optionService = app(\App\Support\Options\Options::class);
    if ($useDefault && ($selected === null || $selected === '') && old($name) === null) {
        $selected = $optionService->defaultId($group);
    }
    $choices = $optionService->for($group, $selected, old($name))
        ->mapWithKeys(fn ($o) => [$o->id => $o->label.($o->is_active ? '' : ' (inactive)')]);
@endphp
<x-form.select :name="$name" :label="$label" :options="$choices" :selected="$selected" :required="$required"
               :placeholder="$placeholder" :help="$help" :col="$col" {{ $attributes }} />
