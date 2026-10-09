<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Rules\NoSecrets;
use App\Support\Options\Options;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductRequest extends HubRequest
{
    public function authorize(): bool
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        return $product
            ? $this->canUpdate($product)
            : Gate::allows('products.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper(trim((string) $this->input('code'))),
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('name'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique('products', 'code')->ignore($product)],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:150', Rule::unique('products', 'slug')->ignore($product)],
            'description' => ['nullable', 'string', 'max:5000', new NoSecrets],
            'primary_owner' => ['nullable', 'string', 'max:150'],
            'status_id' => ['nullable', app(Options::class)->rule('product_status', $product?->status_id)],
            'notes' => ['nullable', 'string', 'max:10000', new NoSecrets],
        ];
    }
}
