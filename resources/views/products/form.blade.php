@php $editing = $product->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$product->code : 'New product'"
               :breadcrumbs="['Products' => route('products.index')] + ($editing ? [$product->code => route('products.show', $product)] : []) + [($editing ? 'Edit' : 'New') => null]">
    <x-page-header :title="$editing ? 'Edit product '.$product->code : 'New product'" />
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ $editing ? route('products.update', $product) : route('products.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif
            <div class="row">
                <x-form.input name="code" label="Product ID" :value="$product->code" required col="col-md-3 mb-3" help="Short stable code, e.g. OTT." />
                <x-form.input name="name" label="Product name" :value="$product->name" required col="col-md-5 mb-3" />
                <x-form.input name="slug" label="Documentation folder (slug)" :value="$product->slug" col="col-md-4 mb-3" help="Used for docs/products/<slug>/. Defaults to the name." />
            </div>
            <x-form.textarea name="description" label="Description" :value="$product->description" />
            <div class="row">
                <x-form.input name="primary_owner" label="Primary owner" :value="$product->primary_owner" col="col-md-6 mb-3" help="Team or person accountable for the product." />
                <x-form.option-select group="product_status" name="status_id" label="Status" :selected="$product->status_id" :use-default="! $product->exists" col="col-md-6 mb-3" />
            </div>
            <x-form.textarea name="notes" label="Notes" :value="$product->notes" rows="4" help="Do not store credentials or secrets." />
            <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create product' }}</button>
            <a href="{{ $editing ? route('products.show', $product) : route('products.index') }}" class="btn btn-link">Cancel</a>
        </form>
    </div></div>
</x-layouts.app>
