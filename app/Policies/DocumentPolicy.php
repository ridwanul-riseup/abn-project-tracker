<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Support\Access\AccessService;

class DocumentPolicy
{
    public function __construct(private readonly AccessService $access) {}

    public function view(User $user, Document $document): bool
    {
        return $this->access->canRead($user, $document);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->access->can($user, 'docs.update', $document)
            && $this->access->canWrite($user, $document);
    }

    public function archive(User $user, Document $document): bool
    {
        return $this->access->can($user, 'docs.archive', $document)
            && $this->access->canWrite($user, $document);
    }
}
