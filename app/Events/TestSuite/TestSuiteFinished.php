<?php

namespace DGLab\Events\TestSuite;

use DGLab\Core\Contracts\EventInterface;

class TestSuiteFinished implements EventInterface
{
    public function __construct(public string $suite, public bool $success, public array $results = [])
    {
    }

    public function getAlias(): string
    {
        return 'test.finished';
    }
}
