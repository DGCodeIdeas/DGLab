<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container\Tests\Fixtures;

class B
{
    public function __construct(public A $a) {}
}