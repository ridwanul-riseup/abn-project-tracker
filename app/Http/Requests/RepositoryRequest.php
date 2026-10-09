<?php

namespace App\Http\Requests;

use App\Models\Application;
use App\Models\Product;
use App\Models\Repository;
use App\Rules\NoSecrets;
use App\Support\Options\Options;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RepositoryRequest extends HubRequest
{
    public function authorize(): bool
    {
        /** @var Repository|null $repository */
        $repository = $this->route('repository');
        if ($repository && ! $this->canUpdate($repository)) {
            return false;
        }

        $productId = (int) $this->input('product_id');
        if ($repository && $productId === $repository->product_id) {
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
        /** @var Repository|null $repository */
        $repository = $this->route('repository');
        $options = app(Options::class);
        $productId = (int) $this->input('product_id');

        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique('repositories', 'code')->ignore($repository)],
            'name' => ['required', 'string', 'max:200'],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'application_id' => ['nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($productId) {
                if (! Application::query()->whereKey($value)->where('product_id', $productId)->exists()) {
                    $fail('The selected application does not belong to the selected product.');
                }
            }],
            'layer_id' => ['nullable', $options->rule('repository_layer', $repository?->layer_id)],
            'url' => ['nullable', 'string', 'max:500', 'url:https,http', new NoSecrets],
            'primary_owner' => ['nullable', 'string', 'max:150'],
            'tech_stack' => ['nullable', 'string', 'max:255'],
            'module_label' => ['nullable', 'string', 'max:150'],
            'database_reference_id' => ['nullable', 'integer', Rule::exists('database_references', 'id')],
            'api_reference' => ['nullable', 'string', 'max:255'],
            'figma_url' => ['nullable', 'string', 'max:1000', 'url:https'],
            'issue_tracker_url' => ['nullable', 'string', 'max:500', 'url:https,http', new NoSecrets],
            'issue_tracker_ref' => ['nullable', 'string', 'max:100'],
            'current_branch' => ['nullable', 'string', 'max:100'],
            'deployment_target_id' => ['nullable', $options->rule('deployment_target', $repository?->deployment_target_id)],
            'status_id' => ['nullable', $options->rule('repository_status', $repository?->status_id)],
            'notes' => ['nullable', 'string', 'max:10000', new NoSecrets],
            'sensitive_notes' => ['nullable', 'string', 'max:10000', new NoSecrets],
            'environment_ids' => ['nullable', 'array'],
            'environment_ids.*' => ['integer', Rule::exists('environments', 'id')],
        ];
    }
}
