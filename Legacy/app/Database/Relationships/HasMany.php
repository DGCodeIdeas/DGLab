<?php

namespace DGLab\Database\Relationships;

use DGLab\Database\Model;

/**
 * Relationship: Has Many
 */
class HasMany
{
    public function __construct(
        private Model $parent,
        private string $related,
        private string $foreignKey,
        private string $localKey
    ) {
    }

    public function get(): array
    {
        $related = new $this->related();

        return $this->related::query()
            ->where($this->foreignKey, $this->parent->getAttribute($this->localKey))
            ->get();
    }
}
