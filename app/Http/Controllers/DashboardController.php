<?php

namespace App\Http\Controllers;

use App\Models\Clarification;
use App\Models\Document;
use App\Models\Module;
use App\Models\Product;
use App\Models\Repository;
use App\Models\Task;
use App\Support\ActivityFeed;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ActivityFeed $feed): View
    {
        $user = $this->user();
        $openIds = $this->options()->statusIdsWithBehavior(['open', 'blocked']);
        $blockedIds = $this->options()->statusIdsWithBehavior(['blocked']);

        $tasks = fn () => Task::query()->visibleTo($user)->with(['status', 'priority', 'product']);

        return view('dashboard', [
            'products' => Product::query()->visibleTo($user)->notArchived()->with('status')->orderBy('code')->get(),
            'repoCounts' => Repository::query()->visibleTo($user)->notArchived()->get(['id', 'product_id'])->countBy('product_id'),
            'moduleCounts' => Module::query()->visibleTo($user)->notArchived()->get(['id', 'product_id'])->countBy('product_id'),
            'counts' => [
                'repositories' => Repository::query()->visibleTo($user)->notArchived()->count(),
                'modules' => Module::query()->visibleTo($user)->notArchived()->count(),
                'documents' => Document::query()->visibleTo($user)->notArchived()->count(),
                'open_tasks' => $tasks()->whereIn('status_id', $openIds)->count(),
                'blocked_tasks' => $tasks()->whereIn('status_id', $blockedIds)->count(),
                'clarifications' => Clarification::query()->visibleTo($user)->open()->count(),
            ],
            'myTasks' => $tasks()->where('assignee_id', $user->id)->whereIn('status_id', $openIds)
                ->orderByRaw('due_date IS NULL')->orderBy('due_date')->limit(8)->get(),
            'blockedTasks' => $tasks()->whereIn('status_id', $blockedIds)->latest('updated_at')->limit(5)->get(),
            'recentDocuments' => Document::query()->visibleTo($user)->notArchived()->with('editor')
                ->latest('content_updated_at')->limit(6)->get(),
            'activity' => $feed->recent($user, 10),
        ]);
    }
}
