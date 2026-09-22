<?php

namespace DGLab\Database\Relationships;

use DGLab\Database\Model;

/**
 * Relationship: Belongs To
 */
class BelongsTo
{
    public function __construct(
        private Model $child,
        private string $related,
        private string $foreignKey,
        private string $ownerKey
    ) {
    }

    public function get(): ?Model
    {
        return $this->related::query()
            ->where($this->ownerKey, $this->child->getAttribute($this->foreignKey))
            ->first();
    }
}
