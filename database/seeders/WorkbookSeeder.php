<?php

namespace Database\Seeders;

use App\Models\ApiReference;
use App\Models\Application;
use App\Models\Clarification;
use App\Models\ComponentDependency;
use App\Models\DatabaseReference;
use App\Models\Deployment;
use App\Models\Environment;
use App\Models\FigmaReference;
use App\Models\Integration;
use App\Models\Module;
use App\Models\Product;
use App\Models\Release;
use App\Models\Repository;
use App\Support\Options\Options;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports the initial project inventory from
 * database/seeders/data/abn_workbook.json, an export of
 * "ABN Project Details for Development Team.xlsx" (see scripts/xlsx_to_json.py).
 *
 * Rules: never invent values; when a value is missing, ambiguous or cannot be
 * mapped safely, keep the original text and create a Clarification item.
 * The seeder is idempotent (records are matched by their workbook IDs).
 */
class WorkbookSeeder extends Seeder
{
    private const SOURCE = 'Workbook';

    /** Product slugs used for documentation folders. */
    private const PRODUCT_SLUGS = [
        'SHR' => 'shared-platform',
        'OTT' => 'ott',
        'ABI' => 'ab-intelligence',
        'ERP' => 'abos-erp',
    ];

    /** @var array<string, list<array<int, string|null>>> */
    private array $sheets = [];

    private Options $options;

    /** @var array<string, Product> */
    private array $products = [];

    /** @var array<string, Repository> */
    private array $repositories = [];

    /** @var array<string, DatabaseReference> */
    private array $databases = [];

    /** @var array<string, Environment> */
    private array $environments = [];

    public function run(): void
    {
        $file = database_path('seeders/data/abn_workbook.json');
        $this->sheets = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        $this->options = app(Options::class);

        $this->importProducts();
        $this->importDatabases();
        $this->importEnvironments();
        $this->importApplications();
        $this->importRepositories();
        $this->importModules();
        $this->importFigma();
        $this->importDependencies();
        $this->importDeployments();
        $this->importReleases();
        $this->importGlobalClarifications();
    }

    private function importProducts(): void
    {
        foreach ($this->rows('02-') as $i => $row) {
            [$code, $name, $description, $dbCode, $owner, $status] = $this->pad($row, 6);

            $notes = match ($code) {
                'ABI' => "The workbook glossary defines ABI as \"AB Intelligence / CRM\". AutonOS is a product/service within the AB Intelligence ecosystem and is tracked as application ABI-AUT.\nWorkbook database reference: {$dbCode}.",
                default => "Workbook database reference: {$dbCode}.",
            };

            $this->products[$code] = Product::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'slug' => self::PRODUCT_SLUGS[$code] ?? Str::slug($name),
                'description' => $description,
                'primary_owner' => $owner,
                'status_id' => $this->option('product_status', $status),
                'notes' => $notes,
            ]);

            $this->clarify($this->products[$code], 'primary_owner',
                'Primary owner is a team label, not a named person/role. Confirm the accountable owner.',
                $owner, $this->src('02-products', $i, $code));
        }
    }

    private function importDatabases(): void
    {
        foreach ($this->rows('06-') as $row) {
            [$code, $name, $productCode, $owner, $usedBy, $systemOfRecord, $environments] = $this->pad($row, 7);

            $this->databases[$code] = DatabaseReference::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'product_id' => $this->products[$productCode]->id ?? null,
                'owner' => $owner,
                'used_by' => $usedBy,
                'system_of_record' => $systemOfRecord,
                'environment_notes' => $environments,
                'notes' => 'Imported from workbook sheet 06-databases. Connection details and credentials are intentionally not stored.',
            ]);
        }
    }

    private function importEnvironments(): void
    {
        $sort = 0;
        foreach ($this->rows('10-') as $row) {
            [$code, $name, $purpose, $deployment] = $this->pad($row, 4);

            $this->environments[$code] = Environment::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'purpose' => $purpose,
                'deployment_method_id' => $this->option('deployment_method', $deployment),
                'status_id' => $this->options->idFor('reference_status', 'needs_clarification'),
                'sort_order' => ($sort += 10),
                'notes' => 'Workbook sheet is titled "environments – discuss with NAS"; the environment list is not yet confirmed.',
            ]);
        }
    }

    private function importApplications(): void
    {
        $types = [
            'Admin Panel' => 'admin_panel',
            'Website' => 'website',
            'Client Portal' => 'client_portal',
            'Actor Dashboard' => 'dashboard',
            'AutonOS' => 'product_application',
        ];
        $layers = ['FE/BE' => 'fe_be', 'Frontend' => 'frontend', 'Backend' => 'backend'];

        foreach ($this->rows('03-') as $i => $row) {
            [$code, $productCode, $name, $layer, $repoIds] = $this->pad($row, 5);

            $application = Application::query()->updateOrCreate(['code' => $code], [
                'product_id' => $this->products[$productCode]->id,
                'name' => $name,
                'application_type_id' => isset($types[$name]) ? $this->options->idFor('application_type', $types[$name]) : null,
                'classification_id' => isset($layers[$layer]) ? $this->options->idFor('application_classification', $layers[$layer]) : null,
                'notes' => "Workbook layer: {$layer}. Workbook repository IDs: {$repoIds}.",
            ]);

            if ($name === 'AutonOS') {
                $this->clarify($application, 'application_type',
                    'AutonOS is listed as an application of AB Intelligence. Its application type was set to "Product Application"; confirm.',
                    $name, $this->src('03-applications', $i, $code));
            }
        }
    }

    private function importRepositories(): void
    {
        $envMap = ['dev' => 'ENV-DEV', 'stage' => 'ENV-STG', 'uat' => 'ENV-UAT', 'prod' => 'ENV-PROD', 'qa' => 'ENV-QA'];
        $targets = ['Web/CDN' => 'web_cdn', 'API Server' => 'api_server'];
        $productsByName = collect($this->products)->keyBy('name');

        foreach ($this->rows('04-') as $i => $row) {
            [, $code, $productName, $appName, $layer, $repository, $module, $db, $api, $figma, $ticket, , $integrations, $environments, $deployment, $status] = $this->pad($row, 16);

            $product = $productsByName[$productName] ?? throw new RuntimeException("Unknown product {$productName}");
            $application = Application::query()->where('product_id', $product->id)->where('name', $appName)->first();
            $isUrl = $repository !== null && str_starts_with($repository, 'https://');
            $name = $isUrl ? preg_replace('/\.git$/', '', basename((string) parse_url($repository, PHP_URL_PATH))) : $repository;

            $repo = Repository::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'product_id' => $product->id,
                'application_id' => $application?->id,
                'layer_id' => $this->option('repository_layer', $layer),
                'url' => $isUrl ? $repository : null,
                'module_label' => $module,
                'database_reference_id' => $this->databases[$db]->id ?? null,
                'api_reference' => $api,
                'figma_url' => $figma !== null && str_starts_with($figma, 'https://www.figma.com/') ? $figma : null,
                'issue_tracker_ref' => $ticket,
                'deployment_target_id' => isset($targets[$deployment]) ? $this->options->idFor('deployment_target', $targets[$deployment]) : null,
                'status_id' => $this->option('repository_status', $status),
                'notes' => "Workbook module label: {$module}. Workbook environments: {$environments}.",
            ]);
            $this->repositories[$code] = $repo;

            $envIds = collect(explode('/', (string) $environments))
                ->map(fn ($e) => $this->environments[$envMap[strtolower(trim($e))] ?? '']->id ?? null)
                ->filter()->values()->all();
            $repo->environments()->sync($envIds);

            $source = $this->src('04-repositories', $i, $code);

            if (! $isUrl) {
                $this->clarify($repo, 'url', 'Repository URL is missing. The workbook provides only a repository name.', $repository, $source);
            }

            foreach (array_filter(array_map('trim', explode(';', (string) $integrations))) as $integrationCode) {
                $repo->integrations()->syncWithoutDetaching([$this->integration($integrationCode, $source)->id]);
            }
        }

        // Backend repositories expose an API base path in the workbook.
        foreach ($this->rows('04-') as $row) {
            [, $code, , , , , , , $api] = $this->pad($row, 9);
            if ($api !== null && str_starts_with($api, '/')) {
                $repo = $this->repositories[$code];
                ApiReference::query()->updateOrCreate(['code' => $code.'-API'], [
                    'name' => $repo->name.' API',
                    'product_id' => $repo->product_id,
                    'repository_id' => $repo->id,
                    'base_path' => $api,
                    'status_id' => $this->options->idFor('reference_status', 'planned'),
                    'notes' => 'Derived from the API column of workbook sheet 04-repositories. No API specification is available yet.',
                ]);
            }
        }

        foreach (['ABI-ADM-FE', 'ABI-AUT-FE'] as $code) {
            if (isset($this->repositories[$code]) && $this->repositories[$code]->figma_url) {
                $this->clarify($this->repositories[$code], 'figma',
                    'Repository has a Figma URL in 04-repositories but no matching record in sheet 07-figma.',
                    $this->repositories[$code]->figma_url, self::SOURCE.' › 04-repositories / 07-figma');
            }
        }
    }

    private function integration(string $code, string $source): Integration
    {
        $existing = Integration::query()->where('code', $code)->first();
        if ($existing) {
            return $existing;
        }

        $parts = explode('-', $code);
        $sourceProduct = $this->products[$parts[0]] ?? null;
        $targetProduct = $this->products[$parts[1] ?? ''] ?? null;

        $integration = Integration::query()->create([
            'code' => $code,
            'name' => $code,
            'source_product_id' => ($sourceProduct ?? $this->products['SHR'])->id,
            'target_product_id' => $targetProduct?->id,
            'status_id' => $this->options->idFor('reference_status', 'needs_clarification'),
            'notes' => $targetProduct === null
                ? "Second segment \"{$parts[1]}\" of the ID is not a product code (it may refer to application {$parts[0]}-{$parts[1]})."
                : null,
        ]);

        $this->clarify($integration, 'description',
            'Integration ID appears in the workbook, but its purpose, protocol and contract are not described.',
            $code, $source);

        return $integration;
    }

    private function importModules(): void
    {
        $appLabels = ['Client Portal' => 'ABI-CP', 'AutonOS' => 'ABI-AUT'];

        foreach ($this->rows('05-') as $i => $row) {
            [$code, $productCode, $appLabel, $name, $primaryRepo] = $this->pad($row, 5);
            $applicationId = isset($appLabels[$appLabel])
                ? Application::query()->where('code', $appLabels[$appLabel])->value('id')
                : null;

            $module = Module::query()->updateOrCreate(['code' => $code], [
                'product_id' => $this->products[$productCode]->id,
                'application_id' => $applicationId,
                'name' => $name,
                'primary_repository_id' => $this->repositories[$primaryRepo]->id ?? null,
                'notes' => "Workbook application label: {$appLabel}.",
            ]);

            if ($code === 'ERP-PROC') {
                $this->clarify($module, 'name',
                    'Module ID suggests "Procurement", but the workbook module name is "ERP". Confirm the module name.',
                    $name, $this->src('05-modules-project-wise', $i, $code));
            }
        }
    }

    private function importFigma(): void
    {
        $types = ['UI/UX' => 'ui_ux'];

        foreach ($this->rows('07-') as $i => $row) {
            [$code, $productCode, $appName, $designType, $repoCode, $file, $status] = $this->pad($row, 7);
            $product = $this->products[$productCode];
            $repo = $this->repositories[$repoCode] ?? null;

            FigmaReference::query()->updateOrCreate(['code' => $code], [
                'name' => $file,
                'product_id' => $product->id,
                'application_id' => Application::query()->where('product_id', $product->id)->where('name', $appName)->value('id'),
                'repository_id' => $repo?->id,
                'design_type_id' => isset($types[$designType]) ? $this->options->idFor('design_type', $types[$designType]) : null,
                'url' => $repo?->figma_url,
                'status_id' => $this->option('reference_status', $status),
                'notes' => $repo?->figma_url
                    ? "URL taken from the Figma column of repository {$repoCode} (sheet 04-repositories)."
                    : 'No Figma URL available in the workbook.',
            ]);
        }
    }

    private function importDependencies(): void
    {
        $sheet = $this->rows('08-');
        foreach ($sheet as $row) {
            [$code, $sourceCode, $targetCode, $type, $criticality, $impact] = $this->pad($row, 6);
            ComponentDependency::query()->updateOrCreate(['code' => $code], [
                'source_type' => 'repository',
                'source_id' => $this->repositories[$sourceCode]->id,
                'target_type' => 'repository',
                'target_id' => $this->repositories[$targetCode]->id,
                'dependency_type_id' => $this->option('dependency_type', $type),
                'criticality_id' => $this->option('criticality', $criticality),
                'failure_impact' => $impact,
                'status_id' => $this->options->idFor('dependency_status', 'active'),
                'description' => 'Imported from workbook sheet 08-dependencies.',
            ]);
        }

        $next = count($sheet) + 1;
        foreach ($this->rows('04-') as $i => $row) {
            [, $code, , , , , , , , , , $dependencies] = $this->pad($row, 12);
            $source = $this->repositories[$code];

            foreach (array_filter(array_map('trim', explode(';', (string) $dependencies))) as $token) {
                [$target, $targetType, $note] = $this->resolveDependencyTarget($token);
                if ($target === null) {
                    $this->clarify($source, 'dependency', "Dependency \"{$token}\" could not be matched to a repository or database.", $token, $this->src('04-repositories', $i, $code));

                    continue;
                }

                $exists = ComponentDependency::query()
                    ->where(['source_type' => 'repository', 'source_id' => $source->id, 'target_type' => $targetType, 'target_id' => $target->getKey()])
                    ->exists();
                if ($exists) {
                    continue;
                }

                $dependency = ComponentDependency::query()->create([
                    'code' => sprintf('DEP-%03d', $next++),
                    'source_type' => 'repository',
                    'source_id' => $source->id,
                    'target_type' => $targetType,
                    'target_id' => $target->getKey(),
                    'dependency_type_id' => $this->options->idFor('dependency_type', $targetType === 'database' ? 'database' : 'unspecified'),
                    'status_id' => $this->options->idFor('dependency_status', 'needs_clarification'),
                    'description' => 'Imported from the Dependency column of workbook sheet 04-repositories. Type of dependency, criticality and failure impact are not specified.',
                    'notes' => $note,
                ]);

                if ($note !== null) {
                    $this->clarify($dependency, 'target', $note, $token, $this->src('04-repositories', $i, $code));
                }
            }
        }
    }

    /**
     * @return array{0: Model|null, 1: string, 2: string|null}
     */
    private function resolveDependencyTarget(string $token): array
    {
        if (isset($this->repositories[$token])) {
            return [$this->repositories[$token], 'repository', null];
        }
        if (isset($this->databases[$token])) {
            return [$this->databases[$token], 'database', null];
        }
        if (strtolower($token) === 'shared db' && isset($this->databases['SHR-DB'])) {
            return [$this->databases['SHR-DB'], 'database', 'Workbook says "Shared DB"; mapped to SHR-DB (shared_db). Confirm.'];
        }

        return [null, '', null];
    }

    private function importDeployments(): void
    {
        $envMap = ['DEV' => 'ENV-DEV', 'QA' => 'ENV-QA', 'UAT' => 'ENV-UAT', 'STAGING' => 'ENV-STG', 'PROD' => 'ENV-PROD'];

        foreach ($this->rows('09-') as $row) {
            [$code, $repoCode, $env, $infrastructure, $method, $branch, $version, $status] = $this->pad($row, 8);

            Deployment::query()->updateOrCreate(['code' => $code], [
                'repository_id' => $this->repositories[$repoCode]->id,
                'environment_id' => $this->environments[$envMap[strtoupper((string) $env)] ?? '']->id ?? null,
                'infrastructure' => $infrastructure,
                'method_id' => $this->option('deployment_method', $method),
                'branch' => $branch,
                'version' => $version,
                'status_id' => $this->option('deployment_status', $status),
                'notes' => 'Imported from workbook sheet 09-deployement. Secret-manager reference not yet provided.',
            ]);
        }
    }

    private function importReleases(): void
    {
        foreach ($this->rows('11-') as $i => $row) {
            [$code, $productCode, $version, $repos, $env, $date, $status] = $this->pad($row, 7);
            $isDate = $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);

            $release = Release::query()->updateOrCreate(['code' => $code], [
                'product_id' => $this->products[$productCode]->id,
                'version' => $version,
                'environment_id' => $this->environments['ENV-'.strtoupper((string) $env)]->id ?? null,
                'release_date' => $isDate ? $date : null,
                'status_id' => $this->option('release_status', $status),
                'notes' => $isDate ? null : "Workbook release date: {$date}.",
            ]);

            $release->repositories()->sync(
                collect(explode(';', (string) $repos))->map(fn ($c) => $this->repositories[trim($c)]->id ?? null)->filter()->all()
            );

            if (! $isDate) {
                $this->clarify($release, 'release_date', 'Release date is not set in the workbook.', $date, $this->src('11-releases', $i, $code));
            }
        }
    }

    private function importGlobalClarifications(): void
    {
        $items = [
            ['environments', 'Repository sheet lists environments as "dev/stage/uat/prod", the database sheet uses "DEV/QA/UAT/PROD", and the environments sheet defines Local, DEV, QA, UAT, Staging and Production. Confirm which environments each repository uses.', 'dev/stage/uat/prod; DEV/QA/UAT/PROD', '04-repositories / 06-databases / 10-environments'],
            ['issue_tracker', 'The "Jira/Ticket" column contains key patterns (e.g. "SHR-*", "OTT-ADM-*") rather than issue-tracker URLs. Provide the tracker project URLs.', 'SHR-*, ERP-*, ABI-*, OTT-* …', '04-repositories'],
            ['repository_details', 'Technology stack, primary owner and current branch are not provided for any repository.', null, '04-repositories'],
            ['module_application', 'The module sheet "Application" column uses labels (Platform, OTT, CRM, ERP) that do not match application IDs. Only "Client Portal" and "AutonOS" were linked to applications.', 'Platform; OTT; CRM; ERP', '05-modules-project-wise'],
            ['credentials', 'The credentials sheet is empty and titled "discuss with NAS". Credentials must not be stored in the hub; agree on a secret manager and record references on deployment records.', null, '12-credentials-discuss-with-nas'],
            ['dependencies', 'Only one dependency (DEP-001) has type, criticality and failure impact. All other dependencies were derived from the repository sheet and need these details.', null, '04-repositories / 08-dependencies'],
        ];

        foreach ($items as [$field, $issue, $original, $sheet]) {
            Clarification::query()->firstOrCreate(
                ['subject_type' => null, 'field' => $field, 'source' => self::SOURCE.' › '.$sheet],
                ['issue' => $issue, 'original_value' => $original, 'status' => Clarification::OPEN],
            );
        }
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Data rows (header removed) of the sheet whose name starts with $prefix.
     *
     * @return list<array<int, string|null>>
     */
    private function rows(string $prefix): array
    {
        foreach ($this->sheets as $name => $rows) {
            if (str_starts_with($name, $prefix)) {
                return array_slice($rows, 1);
            }
        }

        return [];
    }

    /**
     * @param  array<int, string|null>  $row
     * @return list<string|null>
     */
    private function pad(array $row, int $length): array
    {
        $row = array_map(fn ($v) => $v === null || $v === '' || $v === '—' ? null : trim((string) $v), $row);

        return array_pad(array_slice($row, 0, $length), $length, null);
    }

    private function option(string $group, ?string $label): ?int
    {
        if ($label === null) {
            return null;
        }

        $value = Str::of($label)->lower()->replace(['/', ' ', '-'], ['_', '_', '_'])->toString();

        return $this->options->idFor($group, $value)
            ?? $this->options->for($group)->first(fn ($o) => strcasecmp($o->label, $label) === 0)?->id;
    }

    private function src(string $sheet, int $index, ?string $id): string
    {
        return self::SOURCE.' › '.$sheet.' › row '.($index + 2).($id ? " ({$id})" : '');
    }

    private function clarify(Model $subject, string $field, string $issue, ?string $original, string $source): void
    {
        $scope = match (true) {
            $subject instanceof Product => ['product_id' => $subject->id],
            $subject instanceof Application => ['product_id' => $subject->product_id, 'application_id' => $subject->id],
            $subject instanceof Repository => ['product_id' => $subject->product_id, 'repository_id' => $subject->id],
            $subject instanceof Module => ['product_id' => $subject->product_id, 'module_id' => $subject->id],
            $subject instanceof Release => ['product_id' => $subject->product_id],
            $subject instanceof Integration => ['product_id' => $subject->source_product_id],
            $subject instanceof ComponentDependency => $subject->source instanceof Repository
                ? ['product_id' => $subject->source->product_id, 'repository_id' => $subject->source->id]
                : [],
            default => [],
        };

        Clarification::query()->firstOrCreate(
            ['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(), 'field' => $field],
            [...$scope, 'issue' => $issue, 'original_value' => $original, 'source' => $source, 'status' => Clarification::OPEN],
        );
    }
}
