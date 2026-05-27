<?php

namespace DGLab\Services\Superpowers\Exceptions;

class SuperpowersException extends \RuntimeException
{
    private ?string $viewPath;
    private ?int $viewLine;
    private ?string $sourceCode;

    public function __construct(string $message, ?string $viewPath = null, ?int $viewLine = null, ?string $sourceCode = null, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->viewPath = $viewPath;
        $this->viewLine = $viewLine;
        $this->sourceCode = $sourceCode;
    }

    public function getViewPath(): ?string
    {
        return $this->viewPath;
    }
    public function getViewLine(): ?int
    {
        return $this->viewLine;
    }
    public function getSourceCode(): ?string
    {
        return $this->sourceCode;
    }
}
