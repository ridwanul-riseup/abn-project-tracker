@php $editing = $template->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit template' : 'New template'"
               :breadcrumbs="['Global settings' => route('admin.options.index'), 'Document templates' => route('admin.templates.index'), ($editing ? $template->name : 'New') => null]">
    <x-page-header :title="$editing ? 'Edit template: '.$template->name : 'New document template'"
                   :subtitle="'Placeholders: '.implode(', ', \App\Models\DocumentTemplate::PLACEHOLDERS).'. Never include credentials or secrets.'" />
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ $editing ? route('admin.templates.update', $template) : route('admin.templates.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif
            <div class="row">
                <x-form.input name="name" label="Name" :value="$template->name" required maxlength="120" col="col-md-4 mb-3" />
                @if ($editing)
                    <div class="col-md-3 mb-3"><label class="form-label">Key</label><div class="form-control-plaintext"><code>{{ $template->key }}</code> <span class="small text-muted">(fixed)</span></div></div>
                @else
                    <x-form.input name="key" label="Stable key (optional)" help="Lowercase, digits and _. Cannot be changed later." maxlength="60" col="col-md-3 mb-3" />
                @endif
                <x-form.option-select group="document_category" name="category_id" label="Default category" :selected="$template->category_id"
                                      help="Preselected when this template is chosen." col="col-md-5 mb-3" />
                <x-form.input name="description" label="Description" :value="$template->description" maxlength="255" col="col-12 mb-3" />
                <div class="col-12 mb-3">
                    <label class="form-label" for="f_content">Content (Markdown) <span class="text-danger">*</span></label>
                    <textarea id="f_content" name="content" rows="18" required maxlength="60000"
                              class="form-control font-monospace @error('content') is-invalid @enderror">{{ old('content', $template->content) }}</textarea>
                    @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <button class="btn btn-primary">{{ $editing ? 'Save template' : 'Create template' }}</button>
            <a href="{{ route('admin.templates.index') }}" class="btn btn-link">Cancel</a>
        </form>
    </div></div>
</x-layouts.app>
