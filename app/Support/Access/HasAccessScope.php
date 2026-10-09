<?php

namespace App\Support\Access;

/**
 * Implemented by every model whose visibility is governed by scoped access rules.
 */
interface HasAccessScope
{
    /**
     * Direct parent scopes as [type, id] pairs (e.g. ['product', 3]).
     * Ancestors of these parents are resolved by the AccessService.
     *
     * @return list<array{0: string, 1: int}>
     */
    public function accessParents(): array;

    /**
     * Columns required to evaluate access without loading the full record.
     *
     * @return list<string>
     */
    public static function accessColumns(): array;

    /**
     * Whether every active user may read the record unless explicitly denied.
     */
    public function openToAllUsers(): bool;
}
