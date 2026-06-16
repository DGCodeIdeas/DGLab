<?php

namespace DGLab\Events\TestSuite;

use DGLab\Core\Contracts\EventInterface;

class TestSuiteStarted implements EventInterface
{
    public function __construct(public string $suite, public array $context = [])
    {
    }

    public function getAlias(): string
    {
        return 'test.started';
    }
}
