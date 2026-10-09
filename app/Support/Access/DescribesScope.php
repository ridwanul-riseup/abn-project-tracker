<?php

namespace App\Support\Access;

use Illuminate\Database\Eloquent\Model;

/**
 * For records with scope_type / scope_id columns (access rules, role assignments).
 */
trait DescribesScope
{
    /**
     * The scoped record, or null for application-wide entries.
     */
    public function scopeModel(): ?Model
    {
        if ($this->scope_type === 'global' || $this->scope_id === null) {
            return null;
        }

        $class = Model::getActualClassNameForMorph($this->scope_type);

        /** @var class-string<Model> $class */
        return $class::query()->find($this->scope_id);
    }

    public function scopeLabel(): string
    {
        if ($this->scope_type === 'global') {
            return 'Application-wide';
        }

        $model = $this->scopeModel();
        if ($model === null) {
            return ucfirst($this->scope_type).' #'.$this->scope_id.' (deleted)';
        }

        $label = $model->getAttribute('code') ?? $model->getAttribute('path');
        $name = $model->getAttribute('name') ?? $model->getAttribute('title');

        return ucfirst($this->scope_type).': '.trim($label.' — '.$name, ' —');
    }
}
