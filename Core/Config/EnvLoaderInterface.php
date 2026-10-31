<?php

declare(strict_types=1);

namespace SovereignStack\Core\Config;

/**
 * Loads environment variables from a .env file into $_ENV.
 *
 * Implementations MUST:
 *  - Parse KEY=VALUE lines, ignoring blank lines and # comments.
 *  - Honour surrounding quotes (single and double).
 *  - Support `${VAR}` interpolation inside double-quoted values.
 *  - NOT overwrite keys already present in $_ENV (env wins over .env file).
 *  - Write results to $_ENV only — never to getenv(), which is not thread-safe
 *    under FrankenPHP workers (per blueprint §Context7 Research).
 *
 * @package SovereignStack\Core\Config
 */
interface EnvLoaderInterface
{
    /**
     * Load environment variables from a .env file.
     *
     * @param string $path Absolute path to the .env file.
     *
     * @throws Exception\InvalidEnvFileException When the file is missing or unreadable.
     *
     * @return array<string, string> The variables loaded (also written to $_ENV).
     */
    public function load(string $path): array;
}
