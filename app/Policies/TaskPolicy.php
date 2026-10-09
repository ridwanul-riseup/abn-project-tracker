<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Support\Access\AccessService;

class TaskPolicy
{
    public function __construct(private readonly AccessService $access) {}

    public function view(User $user, Task $task): bool
    {
        return $this->access->canRead($user, $task);
    }

    /**
     * Full edit, including scope changes.
     */
    public function update(User $user, Task $task): bool
    {
        return $this->access->can($user, 'tasks.update', $task)
            && $this->access->canWrite($user, $task);
    }

    /**
     * Choosing or changing the assignee.
     */
    public function assign(User $user, Task $task): bool
    {
        return $this->access->can($user, 'tasks.assign', $task)
            && $this->access->canWrite($user, $task);
    }

    /**
     * Status, blocker and completion-notes updates.
     */
    public function updateProgress(User $user, Task $task): bool
    {
        if ($this->update($user, $task)) {
            return true;
        }

        return $task->assignee_id === $user->id
            && $this->access->can($user, 'tasks.update_assigned', $task)
            && $this->access->canRead($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return ($this->access->can($user, 'tasks.comment', $task) && $this->access->canRead($user, $task))
            || $this->update($user, $task);
    }
}
