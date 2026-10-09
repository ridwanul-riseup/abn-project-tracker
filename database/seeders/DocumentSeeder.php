<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Product;
use App\Support\Docs\DocumentService;
use App\Support\Options\Options;
use Illuminate\Database\Seeder;

/**
 * Registers the Markdown files shipped in docs/ so they appear in the hub.
 * Files are not modified; missing files are skipped.
 */
class DocumentSeeder extends Seeder
{
    /**
     * [path, title, category, product code|null, visibility, module code|null]
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string|null, 4: string, 5?: string}>
     */
    public static function documents(): array
    {
        return [
            ['README.md', 'AB Digital Ecosystem — Documentation Home', 'overview', null, 'all_users'],
            ['glossary.md', 'Glossary', 'glossary', null, 'all_users'],
            ['products/shared-platform/overview.md', 'AB Shared Platform — Overview', 'overview', 'SHR', 'inherit'],
            ['products/shared-platform/authentication.md', 'Shared Authentication (SHR-AUTH)', 'modules', 'SHR', 'inherit', 'SHR-AUTH'],
            ['products/ott/overview.md', 'ABN+ OTT — Overview', 'overview', 'OTT', 'inherit'],
            ['products/ab-intelligence/overview.md', 'AB Intelligence — Overview', 'overview', 'ABI', 'inherit'],
            ['products/ab-intelligence/auton-os.md', 'AutonOS', 'overview', 'ABI', 'inherit', 'ABI-AUTONOS'],
            ['products/abos-erp/overview.md', 'ABOS ERP — Overview', 'overview', 'ERP', 'inherit'],
            ['integrations/overview.md', 'Cross-Product Integrations', 'integrations', null, 'all_users'],
            ['deployment/environments.md', 'Environments and Deployment', 'deployment', null, 'all_users'],
            ['databases/overview.md', 'Databases', 'databases', null, 'all_users'],
            ['decisions/README.md', 'Architecture Decision Records', 'decisions', null, 'all_users'],
            ['PROJECT_OVERVIEW.md', 'AB Project Hub — Project Overview', 'hub', null, 'all_users'],
            ['ARCHITECTURE.md', 'AB Project Hub — Architecture', 'hub', null, 'all_users'],
            ['DATABASE_SCHEMA.md', 'AB Project Hub — Database Schema', 'hub', null, 'all_users'],
            ['ACCESS_CONTROL.md', 'AB Project Hub — Access Control', 'hub', null, 'all_users'],
            ['DEVELOPMENT_SETUP.md', 'AB Project Hub — Development Setup', 'hub', null, 'all_users'],
            ['DEVELOPMENT_WORKFLOW.md', 'AB Project Hub — Development Workflow', 'hub', null, 'all_users'],
            ['MARKDOWN_DOCUMENTATION_GUIDE.md', 'Markdown Documentation Guide', 'hub', null, 'all_users'],
            ['DEPLOYMENT.md', 'AB Project Hub — Deployment', 'hub', null, 'all_users'],
            ['SECURITY.md', 'AB Project Hub — Security', 'hub', null, 'all_users'],
            ['TESTING.md', 'AB Project Hub — Testing', 'hub', null, 'all_users'],
            ['KNOWN_LIMITATIONS.md', 'AB Project Hub — Known Limitations', 'hub', null, 'all_users'],
            ['IMPLEMENTATION_STATUS.md', 'AB Project Hub — Implementation Status', 'hub', null, 'all_users'],
        ];
    }

    public function run(): void
    {
        $service = app(DocumentService::class);
        $options = app(Options::class);

        foreach (self::documents() as $doc) {
            [$path, $title, $category, $productCode, $visibility] = $doc;
            $moduleCode = $doc[5] ?? null;

            if (! $service->storage()->exists($path)) {
                $this->command->warn("Skipped missing document file: {$path}");

                continue;
            }

            $service->register([
                'path' => $path,
                'title' => $title,
                'category_id' => $options->idFor('document_category', $category),
                'product_id' => $productCode ? Product::query()->where('code', $productCode)->value('id') : null,
                'module_id' => $moduleCode ? Module::query()->where('code', $moduleCode)->value('id') : null,
                'visibility' => $visibility,
            ]);
        }
    }
}
