<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * URLs and labels for any hub record (used for cross-references, search and audit).
 */
class Links
{
    /** Morph alias => registry type used in /registry/{type}/{id}. */
    public const REGISTRY_TYPES = [
        'database' => 'databases',
        'api' => 'apis',
        'figma' => 'figma',
        'environment' => 'environments',
        'deployment' => 'deployments',
        'release' => 'releases',
        'integration' => 'integrations',
    ];

    public static function url(?Model $model): ?string
    {
        if ($model === null || $model->getKey() === null) {
            return null;
        }

        $type = $model->getMorphClass();

        return match (true) {
            $type === 'product' => route('products.show', $model),
            $type === 'application' => route('applications.show', $model),
            $type === 'repository' => route('repositories.show', $model),
            $type === 'module' => route('modules.show', $model),
            $type === 'document' => route('docs.show', $model),
            $type === 'task' => route('tasks.show', $model),
            $type === 'dependency' => route('dependencies.edit', $model),
            isset(self::REGISTRY_TYPES[$type]) => route('registries.show', [self::REGISTRY_TYPES[$type], $model->getKey()]),
            default => null,
        };
    }

    public static function label(?Model $model, bool $withName = false): string
    {
        if ($model === null) {
            return '—';
        }

        $code = $model->getAttribute('code') ?? $model->getAttribute('key');
        $name = $model->getAttribute('name') ?? $model->getAttribute('title');

        if ($code === null) {
            return (string) ($name ?? class_basename($model).' #'.$model->getKey());
        }

        return $withName && $name ? $code.' — '.$name : (string) $code;
    }
}
