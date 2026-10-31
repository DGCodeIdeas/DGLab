<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container\Tests\Fixtures;

class A
{
    public function __construct(public B $b) {}
}