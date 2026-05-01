<?php

namespace DGLab\Events\TestSuite;

use DGLab\Core\Contracts\EventInterface;

class TestSuiteFailed implements EventInterface
{
    public function __construct(public string $suite, public string $reason, public array $context = []) {}

    public function getAlias(): string
    {
        return 'test.failed';
    }
}
