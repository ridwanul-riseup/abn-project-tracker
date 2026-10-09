<?php

namespace App\Support\Docs;

use Illuminate\Support\Str;

/**
 * Validation and construction of managed document paths.
 *
 * A valid path is relative, uses "/" separators, contains only
 * [A-Za-z0-9._-] in each segment, never starts a segment with "." (so no
 * "..", no hidden files), and ends with ".md".
 */
class DocumentPath
{
    private const SEGMENT = '[A-Za-z0-9][A-Za-z0-9._-]*';

    public static function isValid(string $path): bool
    {
        return strlen($path) <= 255
            && preg_match('#\A'.self::SEGMENT.'(/'.self::SEGMENT.')*\.md\z#', $path) === 1
            && ! str_contains($path, '..');
    }

    public static function normalize(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if (! self::isValid($path)) {
            throw new InvalidDocumentPathException("Invalid document path [{$path}].");
        }

        return $path;
    }

    /**
     * Build the path for a new document from server-side values only.
     */
    public static function build(?string $productSlug, ?string $categoryFolder, string $title, ?string $slug = null): string
    {
        $file = Str::slug($slug !== null && $slug !== '' ? $slug : $title);
        if ($file === '') {
            $file = 'document';
        }
        $file = Str::limit($file, 120, '');

        $folder = $productSlug !== null
            ? 'products/'.Str::slug($productSlug)
            : Str::slug($categoryFolder ?: 'general');

        return self::normalize($folder.'/'.$file.'.md');
    }

    /**
     * Resolve a relative Markdown link (e.g. "../glossary.md") against the
     * directory of the current document. Returns null if it escapes the root.
     */
    public static function resolveRelative(string $currentPath, string $link): ?string
    {
        $base = str_contains($currentPath, '/') ? explode('/', dirname($currentPath)) : [];
        $parts = str_starts_with($link, '/') ? [] : $base;

        foreach (explode('/', ltrim($link, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($parts === []) {
                    return null;
                }
                array_pop($parts);

                continue;
            }
            $parts[] = $segment;
        }

        $resolved = implode('/', $parts);

        return self::isValid($resolved) ? $resolved : null;
    }
}
