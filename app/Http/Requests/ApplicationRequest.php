<?php

namespace App\Http\Requests;

use App\Models\Application;
use App\Models\Product;
use App\Rules\NoSecrets;
use App\Support\Applications\SiteUrlSync;
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

        return $product === null || Gate::allows('create-in', ['applications.create', $product]);
    }

    public function canManageSiteCredentials(): bool
    {
        /** @var Application|null $application */
        $application = $this->route('application');
        if ($application) {
            return Gate::allows('viewCredentials', $application) && Gate::allows('update', $application);
        }

        $product = Product::query()->find((int) $this->input('product_id'));

        return Gate::allows('applications.view_credentials')
            && ($product === null || Gate::allows('create-in', ['applications.create', $product]));
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

        $rules = [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique('applications', 'code')->ignore($application)],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'application_type_id' => ['nullable', $options->rule('application_type', $application?->application_type_id)],
            'classification_id' => ['nullable', $options->rule('application_classification', $application?->classification_id)],
            'description' => ['nullable', 'string', 'max:5000', new NoSecrets],
            'status_id' => ['nullable', $options->rule('application_status', $application?->status_id)],
            'notes' => ['nullable', 'string', 'max:10000', new NoSecrets],
        ];

        if ($this->canManageSiteCredentials()) {
            $rules['site_urls'] = ['nullable', 'array', 'max:'.SiteUrlSync::MAX_URLS];
            $rules['site_urls.*.id'] = ['nullable', 'integer'];
            $rules['site_urls.*.label'] = ['nullable', 'string', 'max:100', new NoSecrets];
            $rules['site_urls.*.url'] = ['required', 'string', 'max:500', 'url:https,http'];
            $rules['site_urls.*.credentials'] = ['nullable', 'array', 'max:'.SiteUrlSync::MAX_CREDENTIALS_PER_URL];
            $rules['site_urls.*.credentials.*.id'] = ['nullable', 'integer'];
            $rules['site_urls.*.credentials.*.title'] = ['required', 'string', 'max:150', new NoSecrets];
            $rules['site_urls.*.credentials.*.account_login'] = ['required', 'string', 'max:255', new NoSecrets];
            $rules['site_urls.*.credentials.*.password'] = ['nullable', 'string', 'max:500'];
            $rules['site_urls.*.credentials.*.short_description'] = ['nullable', 'string', 'max:500', new NoSecrets];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function applicationAttributes(): array
    {
        return $this->safe()->except(['site_urls']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function siteUrls(): array
    {
        if (! $this->canManageSiteCredentials()) {
            return [];
        }

        return array_values((array) $this->input('site_urls', []));
    }
}
