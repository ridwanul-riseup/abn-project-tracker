<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Document;
use App\Models\Module;
use App\Models\Product;
use App\Models\Repository;
use App\Models\Task;
use App\Support\Docs\DocumentService;
use App\Support\Registries;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Global search. Every query is restricted to records the user can read;
 * document content is scanned only for documents already visible to the user.
 */
class SearchController extends Controller
{
    private const LIMIT = 15;

    public function __invoke(Request $request, DocumentService $documents): View
    {
        $q = mb_substr($request->string('q')->trim()->toString(), 0, 100);
        $groups = [];

        if (mb_strlen($q) >= 2) {
            $user = $this->user();
            $like = fn (array $columns) => function ($w) use ($columns, $q) {
                foreach ($columns as $column) {
                    $w->orWhere($column, 'like', '%'.addcslashes($q, '%_\\').'%');
                }
            };

            $simple = [
                'Products' => [Product::class, ['code', 'name', 'description']],
                'Applications' => [Application::class, ['code', 'name', 'description']],
                'Repositories' => [Repository::class, ['code', 'name', 'tech_stack', 'module_label', 'url', 'notes']],
                'Modules' => [Module::class, ['code', 'name', 'description']],
                'Tasks' => [Task::class, ['key', 'title']],
            ];
            foreach ($simple as $label => [$class, $columns]) {
                $groups[$label] = $class::query()->visibleTo($user)->where($like($columns))->limit(self::LIMIT)->get()
                    ->map(fn ($m) => ['model' => $m, 'snippet' => Str::limit((string) ($m->description ?? ''), 140)]);
            }

            $groups['Documents'] = Document::query()->visibleTo($user)->notArchived()->orderBy('title')->get()
                ->map(function (Document $doc) use ($documents, $q) {
                    $meta = mb_stripos($doc->title.' '.$doc->summary.' '.$doc->path, $q) !== false;
                    $content = $documents->read($doc);
                    $pos = mb_stripos($content, $q);
                    if (! $meta && $pos === false) {
                        return null;
                    }
                    $snippet = $pos !== false
                        ? '…'.trim(mb_substr($content, max(0, $pos - 60), 160)).'…'
                        : (string) $doc->summary;

                    return ['model' => $doc, 'snippet' => $snippet];
                })
                ->filter()
                ->take(self::LIMIT)
                ->values();

            foreach (Registries::all() as $type => $definition) {
                $groups[$definition['title']] = $this->access()->restrict($definition['model']::query(), $user)->where($like($definition['search']))->limit(self::LIMIT)->get()
                    ->map(fn ($m) => ['model' => $m, 'snippet' => '']);
            }
        }

        return view('search', ['q' => $q, 'groups' => array_filter($groups, fn ($g) => $g->isNotEmpty())]);
    }
}
