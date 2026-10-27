<?php

declare(strict_types=1);

namespace SovereignStack\Core\View;

/**
 * Minimal template-driver contract.
 *
 * A driver wraps one template engine and is responsible for engine
 * initialization, template resolution, rendering, and engine-specific
 * escaping/configuration. DGLab owns driver selection and the application
 * rendering lifecycle; the driver owns engine semantics.
 */
interface TemplateDriverInterface
{
    /**
     * Render a named template with the supplied context.
     *
     * @param string $template Template name or path relative to the configured root.
     * @param array<string, mixed> $context Variables exposed to the template.
     *
     * @return string Rendered output.
     *
     * @throws \Throwable When the template cannot be loaded or rendered.
     */
    public function render(string $template, array $context = []): string;
}
