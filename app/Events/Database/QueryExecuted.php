<?php

namespace DGLab\Events\Database;

use DGLab\Core\BaseEvent;

class QueryExecuted extends BaseEvent
{
    public function __construct(
        public string $sql,
        public array $bindings,
        public float $time,
        public string $connectionName
    ) {}
}
