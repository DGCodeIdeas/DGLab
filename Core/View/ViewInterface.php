<?php

declare(strict_types=1);

namespace SovereignStack\Core\View;

/**
 * Application-facing rendering API.
 *
 * Implementations delegate to a configured {@see TemplateDriverInterface}.
 * This interface intentionally avoids engine-specific concepts so additional
 * drivers can be added without changing application code.
 */
interface ViewInterface
{
    /**
     * Render a template through the configured driver.
     *
     * @param string $template Template name or path relative to the configured root.
     * @param array<string, mixed> $context Variables exposed to the template.
     */
    public function render(string $template, array $context = []): string;
}
