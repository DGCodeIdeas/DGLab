<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container\Tests\Fixtures;

// This class has a constructor with an unresolvable primitive (no default, no type hint)
class Unresolvable
{
    public function __construct(public string $unresolvableParam) {}
}