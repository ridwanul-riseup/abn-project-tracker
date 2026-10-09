<?php

namespace App\Support;

use App\Models\ApiReference;
use App\Models\DatabaseReference;
use App\Models\Deployment;
use App\Models\Environment;
use App\Models\FigmaReference;
use App\Models\Integration;
use App\Models\Release;
use Illuminate\Database\Eloquent\Model;

/**
 * Field definitions for the simple reference registries. One generic
 * controller and set of views renders all of them.
 *
 * Field types: text, textarea, url, date, number, option (needs "group"),
 * product, application, repository, environment, document, repositories (many-to-many).
 *
 * @phpstan-type RegistryField array{name: string, label: string, type: string, required?: bool, max?: int, mono?: bool, help?: string, relation?: string, group?: string}
 * @phpstan-type RegistryDefinition array{model: class-string<Model>, title: string, singular: string, icon: string, description: string, scope: string|null, with: list<string>, search: list<string>, filters: list<string>, columns: list<string>, order?: string, fields: list<RegistryField>}
 */
class Registries
{
    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return RegistryDefinition
     */
    public static function get(string $type): array
    {
        return self::all()[$type] ?? abort(404);
    }

    /**
     * @return array<string, RegistryDefinition>
     */
    public static function all(): array
    {
        $status = fn (string $group) => ['name' => 'status_id', 'label' => 'Status', 'type' => 'option', 'group' => $group, 'relation' => 'status'];
        $notes = ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'help' => 'Never store credentials, passwords or tokens here.'];

        return [
            'apis' => [
                'model' => ApiReference::class,
                'title' => 'API references',
                'singular' => 'API reference',
                'icon' => 'bi-plug',
                'description' => 'API specifications and base paths.',
                'scope' => 'product_id',
                'with' => ['product', 'repository', 'status'],
                'search' => ['code', 'name', 'base_path'],
                'filters' => ['product_id', 'status_id'],
                'columns' => ['code', 'name', 'product_id', 'repository_id', 'base_path', 'status_id'],
                'fields' => [
                    ['name' => 'code', 'label' => 'API ID', 'type' => 'text', 'required' => true, 'max' => 60],
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 150],
                    ['name' => 'product_id', 'label' => 'Product', 'type' => 'product', 'required' => true, 'relation' => 'product'],
                    ['name' => 'repository_id', 'label' => 'Repository', 'type' => 'repository', 'relation' => 'repository'],
                    ['name' => 'base_path', 'label' => 'Base path', 'type' => 'text', 'max' => 255, 'mono' => true, 'help' => 'e.g. /api/v1/ott/*'],
                    ['name' => 'spec_url', 'label' => 'Specification URL', 'type' => 'url', 'help' => 'OpenAPI/Swagger or documentation link.'],
                    ['name' => 'document_id', 'label' => 'Documentation', 'type' => 'document', 'relation' => 'document'],
                    $status('reference_status'),
                    $notes,
                ],
            ],
            'integrations' => [
                'model' => Integration::class,
                'title' => 'Integrations',
                'singular' => 'Integration',
                'icon' => 'bi-arrow-left-right',
                'description' => 'Important integrations between products and services.',
                'scope' => 'source_product_id',
                'with' => ['sourceProduct', 'targetProduct', 'status', 'repositories'],
                'search' => ['code', 'name', 'description'],
                'filters' => ['source_product_id', 'status_id'],
                'columns' => ['code', 'name', 'source_product_id', 'target_product_id', 'repositories', 'status_id'],
                'fields' => [
                    ['name' => 'code', 'label' => 'Integration ID', 'type' => 'text', 'required' => true, 'max' => 60],
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 150],
                    ['name' => 'source_product_id', 'label' => 'Source product', 'type' => 'product', 'required' => true, 'relation' => 'sourceProduct'],
                    ['name' => 'target_product_id', 'label' => 'Target product', 'type' => 'product', 'relation' => 'targetProduct'],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                    ['name' => 'repositories', 'label' => 'Repositories', 'type' => 'repositories', 'relation' => 'repositories'],
                    ['name' => 'document_id', 'label' => 'Documentation', 'type' => 'document', 'relation' => 'document'],
                    $status('reference_status'),
                    $notes,
                ],
            ],
            'databases' => [
                'model' => DatabaseReference::class,
                'title' => 'Databases',
                'singular' => 'Database',
                'icon' => 'bi-database',
                'description' => 'Database names and ownership. Connection strings and credentials are never stored.',
                'scope' => 'product_id',
                'with' => ['product', 'status'],
                'search' => ['code', 'name', 'owner', 'used_by'],
                'filters' => ['product_id', 'status_id'],
                'columns' => ['code', 'name', 'product_id', 'owner', 'used_by', 'environment_notes', 'status_id'],
                'fields' => [
                    ['name' => 'code', 'label' => 'DB ID', 'type' => 'text', 'required' => true, 'max' => 40],
                    ['name' => 'name', 'label' => 'Database name', 'type' => 'text', 'required' => true, 'max' => 150, 'mono' => true],
                    ['name' => 'product_id', 'label' => 'Product', 'type' => 'product', 'relation' => 'product'],
                    ['name' => 'owner', 'label' => 'Owner', 'type' => 'text', 'max' => 150],
                    ['name' => 'used_by', 'label' => 'Used by', 'type' => 'text', 'max' => 255],
                    ['name' => 'system_of_record', 'label' => 'System of record', 'type' => 'text', 'max' => 150],
                    ['name' => 'environment_notes', 'label' => 'Environments', 'type' => 'text', 'max' => 255],
                    $status('reference_status'),
                    $notes,
                ],
            ],
            'figma' => [
                'model' => FigmaReference::class,
                'title' => 'Figma references',
                'singular' => 'Figma reference',
                'icon' => 'bi-palette',
                'description' => 'Figma files and design references.',
                'scope' => 'product_id',
                'with' => ['product', 'application', 'repository', 'designType', 'status'],
                'search' => ['code', 'name'],
                'filters' => ['product_id', 'status_id'],
                'columns' => ['code', 'name', 'product_id', 'application_id', 'repository_id', 'url', 'status_id'],
                'fields' => [
                    ['name' => 'code', 'label' => 'Figma ID', 'type' => 'text', 'required' => true, 'max' => 60],
                    ['name' => 'name', 'label' => 'Figma file', 'type' => 'text', 'required' => true, 'max' => 150],
                    ['name' => 'product_id', 'label' => 'Product', 'type' => 'product', 'required' => true, 'relation' => 'product'],
                    ['name' => 'application_id', 'label' => 'Application', 'type' => 'application', 'relation' => 'application'],
                    ['name' => 'repository_id', 'label' => 'Repository', 'type' => 'repository', 'relation' => 'repository'],
                    ['name' => 'design_type_id', 'label' => 'Design type', 'type' => 'option', 'group' => 'design_type', 'relation' => 'designType'],
                    ['name' => 'url', 'label' => 'Figma URL', 'type' => 'url', 'max' => 1000],
                    $status('reference_status'),
                    $notes,
                ],
            ],
            'environments' => [
                'model' => Environment::class,
                'title' => 'Environments',
                'singular' => 'Environment',
                'icon' => 'bi-hdd-network',
                'description' => 'Ecosystem-wide environments. Visible to all users; editing requires application-wide write access.',
                'scope' => null,
                'with' => ['deploymentMethod', 'status'],
                'search' => ['code', 'name', 'purpose'],
                'filters' => ['status_id'],
                'order' => 'sort_order',
                'columns' => ['code', 'name', 'purpose', 'deployment_method_id', 'status_id'],
                'fields' => [
                    ['name' => 'code', 'label' => 'Environment ID', 'type' => 'text', 'required' => true, 'max' => 40],
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 100],
                    ['name' => 'purpose', 'label' => 'Purpose', 'type' => 'text', 'max' => 255],
                    ['name' => 'deployment_method_id', 'label' => 'Deployment method', 'type' => 'option', 'group' => 'deployment_method', 'relation' => 'deploymentMethod'],
                    ['name' => 'sort_order', 'label' => 'Sort order', 'type' => 'number'],
                    $status('reference_status'),
                    $notes,
                ],
            ],
            'deployments' => [
                'model' => Deployment::class,
                'title' => 'Deployments',
                'singular' => 'Deployment',
                'icon' => 'bi-cloud-upload',
                'description' => 'Deployment records. Store references to the secret manager, never secret values.',
                'scope' => 'repository_id',
                'with' => ['repository', 'environment', 'method', 'status'],
                'search' => ['code', 'branch', 'version', 'infrastructure'],
                'filters' => ['repository_id', 'status_id'],
                'columns' => ['code', 'repository_id', 'environment_id', 'branch', 'version', 'method_id', 'status_id'],
                'fields' => [
                    ['name' => 'code', 'label' => 'Deployment ID', 'type' => 'text', 'required' => true, 'max' => 60],
                    ['name' => 'repository_id', 'label' => 'Repository', 'type' => 'repository', 'required' => true, 'relation' => 'repository'],
                    ['name' => 'environment_id', 'label' => 'Environment', 'type' => 'environment', 'relation' => 'environment'],
                    ['name' => 'infrastructure', 'label' => 'Infrastructure', 'type' => 'text', 'max' => 100],
                    ['name' => 'method_id', 'label' => 'Method', 'type' => 'option', 'group' => 'deployment_method', 'relation' => 'method'],
                    ['name' => 'branch', 'label' => 'Branch', 'type' => 'text', 'max' => 100, 'mono' => true],
                    ['name' => 'version', 'label' => 'Version', 'type' => 'text', 'max' => 50],
                    ['name' => 'secret_reference', 'label' => 'Secret-manager reference', 'type' => 'text', 'max' => 255, 'mono' => true, 'help' => 'Pointer only, e.g. "AWS Secrets Manager: ott/web-be/dev". Never the secret value.'],
                    ['name' => 'deployed_on', 'label' => 'Deployed on', 'type' => 'date'],
                    $status('deployment_status'),
                    $notes,
                ],
            ],
            'releases' => [
                'model' => Release::class,
                'title' => 'Releases',
                'singular' => 'Release',
                'icon' => 'bi-tag',
                'description' => 'Product release versions.',
                'scope' => 'product_id',
                'with' => ['product', 'environment', 'status', 'repositories'],
                'search' => ['code', 'version'],
                'filters' => ['product_id', 'status_id'],
                'columns' => ['code', 'product_id', 'version', 'environment_id', 'release_date', 'repositories', 'status_id'],
                'fields' => [
                    ['name' => 'code', 'label' => 'Release ID', 'type' => 'text', 'required' => true, 'max' => 60],
                    ['name' => 'product_id', 'label' => 'Product', 'type' => 'product', 'required' => true, 'relation' => 'product'],
                    ['name' => 'version', 'label' => 'Version', 'type' => 'text', 'required' => true, 'max' => 50],
                    ['name' => 'environment_id', 'label' => 'Environment', 'type' => 'environment', 'relation' => 'environment'],
                    ['name' => 'release_date', 'label' => 'Release date', 'type' => 'date'],
                    ['name' => 'repositories', 'label' => 'Repositories', 'type' => 'repositories', 'relation' => 'repositories'],
                    ['name' => 'document_id', 'label' => 'Release notes', 'type' => 'document', 'relation' => 'document'],
                    $status('release_status'),
                    $notes,
                ],
            ],
        ];
    }

    /**
     * @param  RegistryDefinition  $definition
     * @return RegistryField|null
     */
    public static function field(array $definition, string $name): ?array
    {
        foreach ($definition['fields'] as $field) {
            if ($field['name'] === $name) {
                return $field;
            }
        }

        return null;
    }
}
