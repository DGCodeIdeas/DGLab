<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container\Tests\Fixtures;

class WithDefaults
{
    public function __construct(
        public string $defaultParam = 'default_value',
        public int $intParam = 42,
    ) {}
}