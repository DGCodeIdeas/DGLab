<?php

declare(strict_types=1);

namespace SovereignStack\Core\View;

use SovereignStack\Core\Config\ConfigInterface;

final class ViewFactory
{
    /**
     * Create a driver instance from an explicit driver name.
     *
     * @param string $driver Driver name.
     * @param string $templatePath Template root.
     * @param array<string, mixed> $options Driver-specific options.
     */
    public static function createDriver(
        string $driver,
        string $templatePath,
        array $options = [],
    ): TemplateDriverInterface {
        return match ($driver) {
            'twig' => new TwigDriver($templatePath, $options),
            'neospx' => throw new \InvalidArgumentException(
                'NeoSPX driver is not available: NeoSPX parser, compiler, and runtime are not yet implemented. Configure view.driver as "twig".'
            ),
            default => throw new \InvalidArgumentException(
                sprintf('Unknown view driver: %s', $driver)
            ),
        };
    }

    /**
     * Create a View from application configuration.
     *
     * Configuration keys:
     * - view.driver: "twig" (required)
     * - view.path: optional template directory (default: app/Resources/views relative to cwd)
     * - view.options: optional driver options
     *
     * @param ConfigInterface $config
     * @param string|null $templatePath Optional override for template path.
     * @param string|null $cachePath Optional cache directory for Twig.
     */
    public static function fromConfig(
        ConfigInterface $config,
        ?string $templatePath = null,
        ?string $cachePath = null,
    ): View {
        $driverName = $config->get('view.driver');
        if (!is_string($driverName) || $driverName === '') {
            throw new \InvalidArgumentException('Missing view.driver configuration.');
        }

        if ($templatePath === null) {
            $configuredPath = $config->get('view.path');
            if (is_string($configuredPath) && $configuredPath !== '') {
                $templatePath = $configuredPath;
            } else {
                // Default to app/Resources/views relative to current working directory
                $templatePath = getcwd() . '/app/Resources/views';
            }
        }

        if (!is_dir($templatePath)) {
            throw new \InvalidArgumentException(
                sprintf('View template path does not exist or is not a directory: %s', $templatePath)
            );
        }

        $options = $config->get('view.options', []);
        if (!is_array($options)) {
            throw new \InvalidArgumentException('view.options must be an array.');
        }

        if ($driverName === 'twig') {
            if ($cachePath !== null) {
                $options['cache'] = $cachePath;
            }
            if (!isset($options['autoescape'])) {
                $options['autoescape'] = 'html';
            }
        }

        $driver = self::createDriver($driverName, $templatePath, $options);

        return new View($driver);
    }
}
