<?php

namespace DGLab\Core\Contracts;

/**
 * Interface EventInterface
 *
 * Marker interface for all event classes within the DGLab framework.
 */
interface EventInterface
{
    /**
     * Get the unique alias for the event.
     *
     * @return string
     */
    public function getAlias(): string;
}
