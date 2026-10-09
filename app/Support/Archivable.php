<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Archival keeps history intact while hiding records from default lists.
 */
trait Archivable
{
    public function initializeArchivable(): void
    {
        $this->mergeCasts(['archived_at' => 'datetime']);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeNotArchived(Builder $query): void
    {
        $query->whereNull($this->getTable().'.archived_at');
    }

    public function isArchived(): bool
    {
        return $this->getAttribute('archived_at') !== null;
    }
}
