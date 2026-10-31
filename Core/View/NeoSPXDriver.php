<?php

declare(strict_types=1);

namespace SovereignStack\Core\View;

/**
 * Integration point for the future NeoSPX driver (NOT YET USABLE).
 *
 * NeoSPX parsing, compilation, and runtime are not implemented yet. This class
 * exists to document the intended driver boundary. ViewFactory::createDriver()
 * explicitly rejects 'neospx' at construction time with a clear error message.
 *
 * When NeoSPX implementation is ready, this class will be completed and the
 * rejection in ViewFactory will be removed.
 */
final class NeoSPXDriver implements TemplateDriverInterface
{
    public function render(string $template, array $context = []): string
    {
        throw new \RuntimeException(
            'NeoSPX template driver is not implemented yet; configure view.driver as "twig" for working template rendering.'
        );
    }
}
