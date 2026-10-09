@php $editing = $document->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit: '.$document->title : 'New document'"
               :breadcrumbs="['Documentation' => route('docs.index')] + ($editing ? [$document->title => route('docs.show', $document), 'Edit' => null] : ['New' => null])">
    <x-page-header :title="$editing ? 'Edit document' : 'New document'" :subtitle="$editing ? $document->path : 'The file path is generated from the product, category and title.'" />

    @if (! $editing)
        <form method="GET" action="{{ route('docs.create') }}" class="card card-body py-2 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-1" for="tpl">Start from template</label>
                    <select id="tpl" name="template" class="form-select form-select-sm" data-autosubmit>
                        @foreach ($templates as $key => $label)<option value="{{ $key }}" @selected($template === $key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                @foreach (['product_id', 'repository_id', 'module_id'] as $keep)
                    @if ($document->{$keep})<input type="hidden" name="{{ $keep }}" value="{{ $document->{$keep} }}">@endif
                @endforeach
                <div class="col-md-8 small text-muted">Changing the template reloads the editor with the template content.</div>
            </div>
        </form>
    @endif

    @if ($changedOutside ?? false)
        <div class="alert alert-info py-2">This file was changed outside the hub since its last recorded revision. You are editing the current file content.</div>
    @endif

    <form method="POST" action="{{ $editing ? route('docs.update', $document) : route('docs.store') }}">
        @csrf
        @if ($editing)
            @method('PUT')
            <input type="hidden" name="base_hash" value="{{ old('base_hash', $baseHash) }}">
        @endif
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header py-1">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#tab-write">Write</button></li>
                            <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#tab-preview"
                                data-markdown-preview data-source="content" data-target="preview" data-url="{{ route('docs.preview') }}" data-path="{{ $document->path }}">Preview</button></li>
                        </ul>
                    </div>
                    <div class="card-body tab-content">
                        <div class="tab-pane show active" id="tab-write">
                            <textarea id="content" name="content" rows="24" required spellcheck="true"
                                      class="form-control markdown-editor @error('content') is-invalid @enderror">{{ old('content', $content) }}</textarea>
                            @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Markdown (GitHub flavoured). Raw HTML is escaped. Link to other docs with relative paths, e.g. <code>[Glossary](../../glossary.md)</code>. Never paste passwords, tokens or keys.</div>
                        </div>
                        <div class="tab-pane" id="tab-preview"><div id="preview" class="markdown-body"></div></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card"><div class="card-body">
                    <x-form.input name="title" label="Title" :value="$document->title" required maxlength="200" />
                    @if (! $editing)
                        <x-form.input name="slug" label="File name (optional)" :value="null" help="Lowercase letters, digits and dashes. Defaults to the title." />
                    @endif
                    <x-form.option-select group="document_category" name="category_id" label="Category" :selected="$document->category_id" required :placeholder="false" />
                    <x-form.select name="product_id" label="Product" :options="$products" :selected="$document->product_id" placeholder="— Cross-product —" />
                    <x-form.select name="repository_id" label="Repository" :options="$repositories" :selected="$document->repository_id" />
                    <x-form.select name="module_id" label="Module" :options="$modules" :selected="$document->module_id" />
                    <div class="mb-3">
                        <label class="form-label" for="visibility">Visibility</label>
                        <select id="visibility" name="visibility" class="form-select @error('visibility') is-invalid @enderror">
                            @foreach (\App\Models\Document::VISIBILITIES as $value => $label)
                                <option value="{{ $value }}" @selected(old('visibility', $document->visibility) === $value)
                                    @disabled($value === \App\Models\Document::VISIBILITY_ALL_USERS && ! $canSetAllUsers && $document->visibility !== $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('visibility')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <x-form.textarea name="summary" label="Summary" :value="$document->summary" rows="2" />
                    <x-form.input name="change_summary" label="Change note" :value="null" help="Short description of this change for the revision history." />
                    <button class="btn btn-primary w-100">{{ $editing ? 'Save document' : 'Create document' }}</button>
                    <a href="{{ $editing ? route('docs.show', $document) : route('docs.index') }}" class="btn btn-link w-100">Cancel</a>
                </div></div>
            </div>
        </div>
    </form>
</x-layouts.app>
