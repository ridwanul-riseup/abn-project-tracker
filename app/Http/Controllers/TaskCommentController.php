<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Rules\NoSecrets;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskCommentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeView($task);
        Gate::authorize('comment', $task);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000', new NoSecrets],
        ]);

        $task->comments()->create([
            'user_id' => $this->user()->id,
            'body' => $validated['body'],
        ]);
        $task->touch();
        Audit::record('task.commented', $task);

        $lastPage = (int) ceil($task->comments()->count() / max(1, (int) config('hub.detail_per_page', 10)));

        return redirect()->to(route('tasks.show', [$task, 'comments' => $lastPage > 1 ? $lastPage : null]).'#comments')
            ->with('status', 'Comment added.');
    }
}
