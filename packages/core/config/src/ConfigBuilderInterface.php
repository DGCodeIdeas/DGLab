<?php

declare(strict_types=1);

namespace SovereignStack\Core\Config;

/**
 * Builds an immutable {@see ConfigInterface} from PHP config files,
 * environment variables, and inline overrides.
 *
 * Build order (later sources override earlier):
 *   1. PHP config files (return arrays)
 *   2. $_ENV variables (string values only)
 *   3. Inline overrides via {@see withOverride()}
 *
 * After {@see build()} is called, the builder is frozen — further
 * mutations throw. This matches ADR-017's worker-scope discipline:
 * the repository is built once at worker boot and never mutated.
 *
 * @package SovereignStack\Core\Config
 */
interface ConfigBuilderInterface
{
    /**
     * Add a PHP config file to the build.
     *
     * The file must return an array. Keys are merged recursively.
     *
     * @param string $path Absolute path to a PHP file returning array<string, mixed>.
     *
     * @throws Exception\InvalidConfigFileException When the file is missing or does not return an array.
     */
    public function loadFile(string $path): static;

    /**
     * Add an inline override.
     *
     * Applied after all file loads and $_ENV merge. Dot-notation supported.
     *
     * @param string $key Dot-separated path, e.g. "app.debug".
     * @param mixed $value Any value.
     */
    public function withOverride(string $key, mixed $value): static;

    /**
     * Freeze the builder and produce the immutable repository.
     *
     * After this call, further {@see loadFile()} / {@see withOverride()} calls throw.
     */
    public function build(): ConfigInterface;
}
