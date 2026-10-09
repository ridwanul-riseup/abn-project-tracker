<?php

namespace App\Support\Docs;

use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Creates and updates registered documents: writes the .md file, keeps the
 * metadata row in sync, and records a revision entry for every change.
 */
class DocumentService
{
    public function __construct(private readonly DocumentStorage $storage) {}

    public function storage(): DocumentStorage
    {
        return $this->storage;
    }

    public function read(Document $document): string
    {
        return $this->storage->exists($document->path) ? $this->storage->read($document->path) : '';
    }

    public function fileExists(Document $document): bool
    {
        return $this->storage->exists($document->path);
    }

    public function currentHash(Document $document): ?string
    {
        return $this->storage->hash($document->path);
    }

    /**
     * True if the file was modified outside the hub (e.g. via Git) since the last recorded revision.
     */
    public function changedOutsideHub(Document $document): bool
    {
        $hash = $this->currentHash($document);

        return $hash !== null && $document->content_hash !== null && $hash !== $document->content_hash;
    }

    public static function normalizeContent(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        return rtrim($content, "\n")."\n";
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, string $content, User $user, ?string $summary = null): Document
    {
        $path = DocumentPath::normalize($attributes['path']);
        $content = self::normalizeContent($content);

        if (Document::query()->where('path', $path)->exists() || $this->storage->exists($path)) {
            throw new DocumentConflictException('A document with this path already exists.');
        }

        return DB::transaction(function () use ($attributes, $path, $content, $user, $summary) {
            $hash = hash('sha256', $content);

            $document = Document::query()->create([
                ...$attributes,
                'path' => $path,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'owner_id' => $attributes['owner_id'] ?? $user->id,
                'content_hash' => $hash,
                'size_bytes' => strlen($content),
                'content_updated_at' => now(),
            ]);

            $this->storage->write($path, $content);

            $this->revision($document, $user, 'created', $summary ?: 'Document created', null, $content, '');

            return $document;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes  metadata changes (path is never changed here)
     */
    public function update(Document $document, array $attributes, string $content, User $user, ?string $summary, ?string $baseHash): Document
    {
        unset($attributes['path']);
        $content = self::normalizeContent($content);
        $currentHash = $this->currentHash($document);

        if ($baseHash !== null && $currentHash !== null && ! hash_equals($currentHash, $baseHash)) {
            throw new DocumentConflictException('This document was changed by someone else after you opened it. Reload the page, re-apply your changes and save again.');
        }

        return DB::transaction(function () use ($document, $attributes, $content, $user, $summary, $currentHash) {
            $previous = $this->read($document);
            $newHash = hash('sha256', $content);
            $contentChanged = $currentHash === null || ! hash_equals($currentHash, $newHash);

            $document->fill($attributes);
            $metadataChanges = [];
            foreach ($document->getDirty() as $key => $value) {
                $metadataChanges[$key] = ['from' => $document->getOriginal($key), 'to' => $value];
            }

            if (! $contentChanged && $metadataChanges === []) {
                return $document;
            }

            $document->updated_by = $user->id;
            if ($contentChanged) {
                $document->content_hash = $newHash;
                $document->size_bytes = strlen($content);
                $document->content_updated_at = now();
            }
            $document->save();

            if ($contentChanged) {
                $this->storage->write($document->path, $content);
            }

            $this->revision(
                $document,
                $user,
                $contentChanged ? 'updated' : 'metadata_updated',
                $summary ?: ($contentChanged ? 'Content updated' : 'Metadata updated'),
                $currentHash,
                $content,
                $previous,
                $metadataChanges,
            );

            return $document;
        });
    }

    /**
     * Register an existing file (seeding / scanning). Does not modify the file.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function register(array $attributes, ?User $user = null): Document
    {
        $path = DocumentPath::normalize($attributes['path']);
        $content = $this->storage->read($path);
        $hash = hash('sha256', $content);

        $document = Document::query()->updateOrCreate(['path' => $path], [
            ...$attributes,
            'content_hash' => $hash,
            'size_bytes' => strlen($content),
            'content_updated_at' => now(),
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        if ($document->wasRecentlyCreated) {
            $this->revision($document, $user, 'registered', 'Existing file registered', null, $content, '');
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $metadataChanges
     */
    private function revision(Document $document, ?User $user, string $action, string $summary, ?string $previousHash, string $content, string $previous, array $metadataChanges = []): void
    {
        $lines = substr_count($content, "\n");
        $previousLines = $previous === '' ? 0 : substr_count($previous, "\n");

        DocumentRevision::query()->create([
            'document_id' => $document->id,
            'user_id' => $user?->id,
            'action' => $action,
            'summary' => mb_substr($summary, 0, 255),
            'content_hash' => hash('sha256', $content),
            'previous_hash' => $previousHash,
            'size_bytes' => strlen($content),
            'line_count' => $lines,
            'lines_delta' => $lines - $previousLines,
            'metadata_changes' => $metadataChanges === [] ? null : $metadataChanges,
        ]);

        Audit::record('document.'.$action, $document, array_filter([
            'summary' => $summary,
            'metadata' => $metadataChanges ?: null,
        ]));
    }

    /**
     * @return array{view: string, history: string}|null
     */
    public function repositoryLinks(Document $document): ?array
    {
        $base = rtrim((string) config('hub.docs.repo_url'), '/');
        if ($base === '' || ! str_starts_with($base, 'https://')) {
            return null;
        }

        $branch = rawurlencode((string) config('hub.docs.repo_branch'));
        $relativeRoot = trim((string) config('hub.docs.path'), '/');
        $filePath = implode('/', array_map('rawurlencode', explode('/', $relativeRoot.'/'.$document->path)));

        return [
            'view' => "{$base}/blob/{$branch}/{$filePath}",
            'history' => "{$base}/commits/{$branch}/{$filePath}",
        ];
    }
}
