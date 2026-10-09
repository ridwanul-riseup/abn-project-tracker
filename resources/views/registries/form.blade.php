@php $editing = $record->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$record->code : 'New '.strtolower($definition['singular'])"
               :breadcrumbs="[$definition['title'] => route('registries.index', $type)] + ($editing ? [$record->code => route('registries.show', [$type, $record->id])] : []) + [($editing ? 'Edit' : 'New') => null]">
    <x-page-header :title="$editing ? 'Edit '.strtolower($definition['singular']).' '.$record->code : 'New '.strtolower($definition['singular'])" :subtitle="$definition['description']" />
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ $editing ? route('registries.update', [$type, $record->id]) : route('registries.store', $type) }}">
            @csrf
            @if ($editing) @method('PUT') @endif
            <div class="row">
                @foreach ($definition['fields'] as $field)
                    @php
                        $name = $field['name'];
                        $required = ! empty($field['required']);
                        $help = $field['help'] ?? null;
                        $col = in_array($field['type'], ['textarea', 'repositories'], true) ? 'col-12 mb-3' : 'col-md-6 mb-3';
                    @endphp
                    @switch($field['type'])
                        @case('textarea')
                            <x-form.textarea :name="$name" :label="$field['label']" :value="$record->getAttribute($name)" :required="$required" :help="$help" :col="$col" />
                            @break
                        @case('option')
                            <x-form.option-select :group="$field['group']" :name="$name" :label="$field['label']" :selected="$record->getAttribute($name)" :required="$required" :help="$help" :col="$col" />
                            @break
                        @case('product')
                        @case('application')
                        @case('repository')
                        @case('document')
                        @case('environment')
                            <x-form.select :name="$name" :label="$field['label']" :options="$choices[$name]" :selected="$record->getAttribute($name)" :required="$required" :help="$help" :col="$col" />
                            @break
                        @case('repositories')
                            <x-form.select :name="$name.'[]'" :label="$field['label']" :options="$choices[$name]" :selected="$editing ? $record->{$field['relation']}->pluck('id') : []" :multiple="true" :help="'Ctrl/Cmd-click to select several.'" :col="'col-md-6 mb-3'" />
                            @break
                        @case('url')
                            <x-form.input :name="$name" :label="$field['label']" type="url" :value="$record->getAttribute($name)" :required="$required" :help="$help" :col="$col" />
                            @break
                        @case('date')
                            <x-form.input :name="$name" :label="$field['label']" type="date" :value="$record->getAttribute($name)" :required="$required" :help="$help" :col="$col" />
                            @break
                        @case('number')
                            <x-form.input :name="$name" :label="$field['label']" type="number" :value="$record->getAttribute($name)" :required="$required" :help="$help" :col="$col" />
                            @break
                        @default
                            <x-form.input :name="$name" :label="$field['label']" :value="$record->getAttribute($name)" :required="$required" :help="$help" :col="$col" />
                    @endswitch
                @endforeach
            </div>
            <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create' }}</button>
            <a href="{{ $editing ? route('registries.show', [$type, $record->id]) : route('registries.index', $type) }}" class="btn btn-link">Cancel</a>
        </form>
    </div></div>
</x-layouts.app>
