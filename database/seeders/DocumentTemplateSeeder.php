<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Support\Options\Options;
use Illuminate\Database\Seeder;

/**
 * Loads the initial document templates from resources/doc-templates into the
 * database. Existing templates (matched by key) are never modified, and
 * templates deleted by an administrator are not recreated once any exist.
 */
class DocumentTemplateSeeder extends Seeder
{
    /**
     * key => [name, description, document_category value|null]
     *
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    public static function templates(): array
    {
        return [
            'blank' => ['Blank document', 'Title only.', null],
            'overview' => ['Product / component overview', 'Purpose, scope, components, owners and open questions.', 'overview'],
            'architecture' => ['Architecture', 'Context, components, data flow and decisions.', 'architecture'],
            'module' => ['Module specification', 'Responsibilities, interfaces, data and status of a module.', 'modules'],
            'api' => ['API reference', 'Endpoints, authentication and error handling (no secrets).', 'apis'],
            'integration' => ['Integration', 'Systems involved, data exchanged and failure handling.', 'integrations'],
            'decision' => ['Architecture decision record (ADR)', 'Context, decision, consequences.', 'decisions'],
            'troubleshooting' => ['Troubleshooting guide', 'Symptoms, causes and resolution steps.', 'troubleshooting'],
        ];
    }

    public function run(): void
    {
        if (DocumentTemplate::query()->exists()) {
            $this->command->info('Document templates already exist — skipped.');

            return;
        }

        $options = app(Options::class);
        $sort = 0;

        foreach (self::templates() as $key => [$name, $description, $category]) {
            $file = resource_path("doc-templates/{$key}.md");

            DocumentTemplate::query()->create([
                'key' => $key,
                'name' => $name,
                'description' => $description,
                'category_id' => $category !== null ? $options->idFor('document_category', $category) : null,
                'content' => is_file($file) ? (string) file_get_contents($file) : "# {{title}}\n",
                'sort_order' => ++$sort,
                'is_active' => true,
            ]);
        }
    }
}
