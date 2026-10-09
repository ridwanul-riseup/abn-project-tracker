<?php

namespace App\Http\Requests;

use App\Models\Application;
use App\Models\Module;
use App\Models\Product;
use App\Models\Repository;
use App\Rules\NoSecrets;
use App\Support\Options\Options;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ModuleRequest extends HubRequest
{
    public function authorize(): bool
    {
        /** @var Module|null $module */
        $module = $this->route('module');
        if ($module && ! $this->canUpdate($module)) {
            return false;
        }

        $productId = (int) $this->input('product_id');
        if ($module && $productId === $module->product_id) {
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
        /** @var Module|null $module */
        $module = $this->route('module');
        $options = app(Options::class);
        $productId = (int) $this->input('product_id');
        $readableRepository = function (string $attribute, mixed $value, Closure $fail) {
            $repo = is_numeric($value) ? Repository::query()->find((int) $value) : null;
            if ($repo === null || ! $this->access()->canRead($this->hubUser(), $repo)) {
                $fail('One of the selected repositories is invalid.');
            }
        };

        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique('modules', 'code')->ignore($module)],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'application_id' => ['nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($productId) {
                if (! Application::query()->whereKey($value)->where('product_id', $productId)->exists()) {
                    $fail('The selected application does not belong to the selected product.');
                }
            }],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000', new NoSecrets],
            'primary_repository_id' => ['nullable', 'integer', $readableRepository],
            'related_repository_ids' => ['nullable', 'array'],
            'related_repository_ids.*' => ['integer', $readableRepository],
            'api_reference' => ['nullable', 'string', 'max:255'],
            'database_reference_id' => ['nullable', 'integer', Rule::exists('database_references', 'id')],
            'figma_url' => ['nullable', 'string', 'max:1000', 'url:https'],
            'owner' => ['nullable', 'string', 'max:150'],
            'status_id' => ['nullable', $options->rule('module_status', $module?->status_id)],
            'priority_id' => ['nullable', $options->rule('priority', $module?->priority_id)],
            'notes' => ['nullable', 'string', 'max:10000', new NoSecrets],
        ];
    }
}
