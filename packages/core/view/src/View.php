<?php

declare(strict_types=1);

namespace SovereignStack\Core\View;

final class View implements ViewInterface
{
    public function __construct(
        private readonly TemplateDriverInterface $driver,
    ) {
    }

    public function render(string $template, array $context = []): string
    {
        return $this->driver->render($template, $context);
    }

    public function getDriver(): TemplateDriverInterface
    {
        return $this->driver;
    }
}
