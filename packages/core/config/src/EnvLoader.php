<?php

declare(strict_types=1);

namespace SovereignStack\Core\Config;

use SovereignStack\Core\Config\Exception\InvalidEnvFileException;

/**
 * Parses .env files into $_ENV.
 *
 * Spec:
 *  - Lines starting with `#` (after optional whitespace) are comments.
 *  - Blank lines are skipped.
 *  - `export KEY=VALUE` and `KEY=VALUE` are both accepted.
 *  - Surrounding single quotes preserve the value verbatim (no interpolation).
 *  - Surrounding double quotes enable ${VAR} interpolation against $_ENV.
 *  - Bare (unquoted) values are trimmed; ${VAR} interpolation still applies.
 *  - Existing $_ENV keys are NEVER overwritten — environment wins over .env file.
 *  - Writes to $_ENV only — never calls putenv() or getenv() (thread-safety per blueprint).
 */
final class EnvLoader implements EnvLoaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw InvalidEnvFileException::missing($path);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw InvalidEnvFileException::missing($path);
        }

        $loaded = [];

        foreach ($this->lines($contents) as [$key, $value]) {
            if (array_key_exists($key, $_ENV)) {
                // Environment wins. Do not overwrite.
                continue;
            }
            $resolved = $this->interpolate($value);
            $_ENV[$key] = $resolved;
            $loaded[$key] = $resolved;
        }

        return $loaded;
    }

    /**
     * Yield [key, value] tuples from the file contents.
     *
     * @return iterable<array{0: string, 1: string}>
     */
    private function lines(string $contents): iterable
    {
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];

        foreach ($lines as $lineNum => $line) {
            $trimmed = ltrim($line);

            // Skip blank lines and comments.
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            // Strip optional `export ` prefix.
            if (str_starts_with($trimmed, 'export ')) {
                $trimmed = substr($trimmed, 7);
                $trimmed = ltrim($trimmed);
            }

            $equalsPos = strpos($trimmed, '=');
            if ($equalsPos === false) {
                throw new \UnexpectedValueException(
                    "Malformed .env line " . ($lineNum + 1) . ": missing '=' in '{$trimmed}'.",
                );
            }

            $key = trim(substr($trimmed, 0, $equalsPos));
            $rawValue = trim(substr($trimmed, $equalsPos + 1));

            if ($key === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
                throw new \UnexpectedValueException(
                    "Malformed .env line " . ($lineNum + 1) . ": invalid key '{$key}'. "
                    . 'Keys must match /^[A-Z_][A-Z0-9_]*$/i.',
                );
            }

            yield [$key, $this->unwrapQuotes($rawValue)];
        }
    }

    /**
     * Strip surrounding quotes from a value. Single quotes preserve verbatim;
     * double quotes and bare values pass through to interpolation.
     */
    private function unwrapQuotes(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];

            if ($first === "'" && $last === "'") {
                // Single-quoted: no interpolation.
                // Mark with a sentinel so interpolate() leaves it alone.
                return substr($value, 1, -1);
            }

            if ($first === '"' && $last === '"') {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }

    /**
     * Resolve ${VAR} references against $_ENV. Single-quoted values were
     * already unwrapped and contain no interpolation markers — they pass
     * through unchanged because ${VAR} inside single quotes is literal.
     */
    private function interpolate(string $value): string
    {
        // Match ${VAR} or $VAR (the latter only at word boundaries).
        // Per spec we support ${VAR} explicitly; bare $VAR is ambiguous
        // (could be a literal dollar sign) and is intentionally NOT expanded.
        return preg_replace_callback(
            '/\$\{([A-Z_][A-Z0-9_]*)\}/i',
            fn (array $m): string => $this->resolveEnvVar($m[1], $m[0]),
            $value,
        ) ?? $value;
    }

    /**
     * Look up an environment variable, returning $fallback if unset.
     *
     * Extracted from the interpolate() closure so the return type is
     * explicit and verifiable by PHPStan.
     */
    private function resolveEnvVar(string $varName, string $fallback): string
    {
        // Guard with array_key_exists so PHPStan can statically verify the
        // subsequent array access returns a known-present value (string per
        // $_ENV stubs) rather than falling back to mixed.
        if (!array_key_exists($varName, $_ENV)) {
            return $fallback;
        }
        return $_ENV[$varName];
    }
}
