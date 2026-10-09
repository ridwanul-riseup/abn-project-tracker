<?php

namespace App\Support\Docs;

use RuntimeException;

/**
 * Stores Markdown files inside a single root directory of the repository.
 * Rejects symlinks and any path that would resolve outside the root.
 */
class LocalMarkdownStorage implements DocumentStorage
{
    private string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
    }

    public function root(): string
    {
        return $this->root;
    }

    public function exists(string $path): bool
    {
        $full = $this->resolve($path);

        return is_file($full);
    }

    public function read(string $path): string
    {
        $full = $this->resolve($path);

        if (! is_file($full)) {
            throw new RuntimeException("Document file [{$path}] does not exist.");
        }

        $content = file_get_contents($full);
        if ($content === false) {
            throw new RuntimeException("Document file [{$path}] could not be read.");
        }

        return $content;
    }

    public function write(string $path, string $content): void
    {
        $full = $this->resolve($path);
        $dir = dirname($full);

        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("Could not create directory for [{$path}].");
        }

        // Re-check after directory creation (defends against races with symlinks).
        $this->assertInsideRoot($full);

        $tmp = $dir.'/.'.basename($full).'.'.bin2hex(random_bytes(6)).'.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            throw new RuntimeException("Could not write document [{$path}].");
        }
        @chmod($tmp, 0664);

        if (! rename($tmp, $full)) {
            @unlink($tmp);
            throw new RuntimeException("Could not save document [{$path}].");
        }
    }

    public function hash(string $path): ?string
    {
        $full = $this->resolve($path);

        return is_file($full) ? (hash_file('sha256', $full) ?: null) : null;
    }

    public function allPaths(): array
    {
        if (! is_dir($this->root)) {
            return [];
        }

        $paths = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isLink() || ! $file->isFile()) {
                continue;
            }
            $relative = ltrim(substr($file->getPathname(), strlen($this->root)), '/');
            if (DocumentPath::isValid($relative)) {
                $paths[] = $relative;
            }
        }

        sort($paths);

        return $paths;
    }

    private function resolve(string $path): string
    {
        $path = DocumentPath::normalize($path);
        $full = $this->root.'/'.$path;
        $this->assertInsideRoot($full);

        return $full;
    }

    private function assertInsideRoot(string $full): void
    {
        $rootReal = realpath($this->root);
        if ($rootReal === false) {
            if (! mkdir($this->root, 0775, true) && ! is_dir($this->root)) {
                throw new RuntimeException('Documentation root does not exist.');
            }
            $rootReal = (string) realpath($this->root);
        }

        // No component of the path (below the root) may be a symlink.
        $relative = substr($full, strlen($this->root) + 1);
        $current = $this->root;
        foreach (explode('/', $relative) as $segment) {
            $current .= '/'.$segment;
            if (is_link($current)) {
                throw new InvalidDocumentPathException('Symbolic links are not allowed in document paths.');
            }
        }

        $existing = $full;
        while (! file_exists($existing) && $existing !== $this->root) {
            $existing = dirname($existing);
        }
        $real = realpath($existing);

        if ($real === false || ($real !== $rootReal && ! str_starts_with($real, $rootReal.'/'))) {
            throw new InvalidDocumentPathException('Document path resolves outside the documentation root.');
        }
    }
}
