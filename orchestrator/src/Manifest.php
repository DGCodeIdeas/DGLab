<?php

declare(strict_types=1);

namespace SovereignStack\Orchestrator;

/**
 * Read and modify a package's composer.json manifest.
 *
 * Used by `loom version:release` to bump the "version" field in the same
 * commit as the SemVer tag (P2 gap 10). Keeping this in a dedicated class
 * (rather than inlining JSON manipulation in bin/loom) makes the atomic
 * write+rename behaviour and the JSON-encoding flags testable.
 */
final class Manifest
{
    /**
     * Set the "version" field in a composer.json file.
     *
     * The file is written atomically (write to a .tmp sibling, then rename)
     * so a partial write never leaves a corrupt manifest on disk. Existing
     * key ordering is preserved by passing the decoded associative array
     * back through json_encode with JSON_PRESERVE_ZERO_FRACTION off and
     * JSON_PRETTY_PRINT on; PHP's json_encode iterates arrays in insertion
     * order, which matches the order keys appeared in the source file.
     *
     * @param string $composerJsonPath Absolute path to composer.json.
     * @param string $version          Bare SemVer (e.g. "1.2.0").
     * @throws \RuntimeException If the version is not bare SemVer, the file
     *                           cannot be read, parsed, or written.
     */
    public static function setVersion(string $composerJsonPath, string $version): void
    {
        if (!\preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new \RuntimeException("Invalid SemVer version: {$version}");
        }

        if (!\is_file($composerJsonPath) || !\is_readable($composerJsonPath)) {
            throw new \RuntimeException("Failed to read composer.json at {$composerJsonPath}");
        }

        $contents = \file_get_contents($composerJsonPath);
        if ($contents === false) {
            throw new \RuntimeException("Failed to read composer.json at {$composerJsonPath}");
        }

        $json = \json_decode($contents, true);
        if (!\is_array($json)) {
            throw new \RuntimeException("composer.json at {$composerJsonPath} is not valid JSON");
        }

        $json['version'] = $version;

        $encoded = \json_encode(
            $json,
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
        );
        if ($encoded === false) {
            throw new \RuntimeException("Failed to encode composer.json at {$composerJsonPath}");
        }

        // POSIX-compliant text files end with a newline.
        $encoded .= "\n";

        $tmp = $composerJsonPath . '.loom-tmp';
        $written = \file_put_contents($tmp, $encoded);
        if ($written === false) {
            throw new \RuntimeException("Failed to write temporary composer.json at {$tmp}");
        }

        if (!\rename($tmp, $composerJsonPath)) {
            @\unlink($tmp);
            throw new \RuntimeException("Failed to replace composer.json at {$composerJsonPath}");
        }
    }

    /**
     * Read the "version" field from a composer.json file.
     *
     * @param string $composerJsonPath Absolute path to composer.json.
     * @return string The version string, or "" if the field is absent.
     * @throws \RuntimeException If the file cannot be read or parsed.
     */
    public static function getVersion(string $composerJsonPath): string
    {
        if (!\is_file($composerJsonPath) || !\is_readable($composerJsonPath)) {
            throw new \RuntimeException("Failed to read composer.json at {$composerJsonPath}");
        }

        $contents = \file_get_contents($composerJsonPath);
        if ($contents === false) {
            throw new \RuntimeException("Failed to read composer.json at {$composerJsonPath}");
        }

        $json = \json_decode($contents, true);
        if (!\is_array($json)) {
            throw new \RuntimeException("composer.json at {$composerJsonPath} is not valid JSON");
        }

        $version = $json['version'] ?? '';
        return \is_string($version) ? $version : '';
    }
}
