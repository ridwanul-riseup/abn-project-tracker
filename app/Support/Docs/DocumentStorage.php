<?php

namespace App\Support\Docs;

/**
 * Storage abstraction for Markdown content. Paths are always relative to the
 * managed documentation root and must pass DocumentPath::normalize().
 */
interface DocumentStorage
{
    public function exists(string $path): bool;

    public function read(string $path): string;

    public function write(string $path, string $content): void;

    public function hash(string $path): ?string;

    /**
     * All Markdown files under the root (relative paths).
     *
     * @return list<string>
     */
    public function allPaths(): array;

    public function root(): string;
}
