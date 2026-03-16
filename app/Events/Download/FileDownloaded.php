<?php

namespace DGLab\Events\Download;

use DGLab\Core\BaseEvent;

class FileDownloaded extends BaseEvent
{
    public function __construct(
        public string $path,
        public string $driver,
        public int $status,
        public float $latency,
        public int $bytes
    ) {}
}
