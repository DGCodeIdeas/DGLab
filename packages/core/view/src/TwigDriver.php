<?php

declare(strict_types=1);

namespace SovereignStack\Core\View;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Permanent Twig template driver.
 *
 * Uses Twig's native filesystem loader and environment. Template resolution
 * is constrained to the configured template root by Twig's loader.
 */
final class TwigDriver implements TemplateDriverInterface
{
    private readonly Environment $environment;

    /**
     * @param string $templatePath Absolute or relative template root.
     * @param array<string, mixed> $options Twig environment options.
     */
    public function __construct(
        string $templatePath,
        array $options = [],
    ) {
        $loader = new FilesystemLoader($templatePath);
        $this->environment = new Environment($loader, $options);
    }

    public function render(string $template, array $context = []): string
    {
        return $this->environment->render($template, $context);
    }
}
