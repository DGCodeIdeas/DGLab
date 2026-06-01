<?php

namespace DGLab\Events\Download;

use DGLab\Core\BaseEvent;

class DownloadFailed extends BaseEvent
{
    public function __construct(
        public string $path,
        public string $driver,
        public int $status,
        public string $reason
    ) {
    }
}
