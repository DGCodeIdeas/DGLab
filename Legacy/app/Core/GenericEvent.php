<?php

namespace DGLab\Core;

/**
 * Generic Event
 *
 * Allows dispatching events using a simple name/alias and payload without creating dedicated classes.
 */
class GenericEvent extends BaseEvent
{
    protected string $name;
    protected array $payload;

    public function __construct(string $name, array $payload = [])
    {
        $this->name = $name;
        $this->payload = $payload;
    }

    public function getAlias(): string
    {
        return $this->name;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function __get(string $key)
    {
        return $this->payload[$key] ?? null;
    }
}
