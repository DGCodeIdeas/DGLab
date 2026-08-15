<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container\Tests\Fixtures;

class WithParams
{
    public function __construct(
        public string $customParam = 'default',
    ) {}
}