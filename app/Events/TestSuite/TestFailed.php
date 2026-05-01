<?php

namespace DGLab\Events\TestSuite;

use DGLab\Core\Contracts\EventInterface;

class TestFailed implements EventInterface
{
    private string $type;
    private string $message;
    private array $details;

    public function __construct(string $type, string $message, array $details = [])
    {
        $this->type = $type;
        $this->message = $message;
        $this->details = $details;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function getName(): string
    {
        return 'test_suite.failed';
    }

    public function getAlias(): string
    {
        return 'test_suite.failed';
    }
}
