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

        return redirect()->to(route('tasks.show', $task).'#comments')->with('status', 'Comment added.');
    }
}
