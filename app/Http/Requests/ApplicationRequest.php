<?php

namespace App\Http\Requests;

use App\Models\Application;
use App\Models\Product;
use App\Rules\NoSecrets;
use App\Support\Options\Options;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ApplicationRequest extends HubRequest
{
    public function authorize(): bool
    {
        /** @var Application|null $application */
        $application = $this->route('application');
        if ($application && ! $this->canUpdate($application)) {
            return false;
        }

        $productId = (int) $this->input('product_id');
        if ($application && $productId === $application->product_id) {
            return true;
        }

        $product = Product::query()->find($productId);

        return $product === null || Gate::allows('inventory.create-in', $product);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => Str::upper(trim((string) $this->input('code')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Application|null $application */
        $application = $this->route('application');
        $options = app(Options::class);

        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique('applications', 'code')->ignore($application)],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'application_type_id' => ['nullable', $options->rule('application_type', $application?->application_type_id)],
            'classification_id' => ['nullable', $options->rule('application_classification', $application?->classification_id)],
            'description' => ['nullable', 'string', 'max:5000', new NoSecrets],
            'status_id' => ['nullable', $options->rule('application_status', $application?->status_id)],
            'notes' => ['nullable', 'string', 'max:10000', new NoSecrets],
        ];
    }
}
