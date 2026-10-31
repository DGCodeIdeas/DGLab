<?php

declare(strict_types=1);

namespace SovereignStack\Orchestrator;

/**
 * Value object representing a single package inside the monorepo.
 *
 * Carries the three pieces of state that RepoManager needs to operate on a
 * monorepo package rather than a standalone clone:
 *   - name:       the logical package identifier (e.g. "core/container")
 *   - path:       the package's directory relative to the repo root
 *                 (e.g. "packages/core/container")
 *   - tagPrefix:  the git-tag namespace prefix (e.g. "core-container")
 *   - legacyTagPattern: optional regex matching grandfathered tag-name forms
 *                 (used by core/container, whose inaugural v1.0.0 tag is
 *                 unprefixed — see Architecture/Core/CORE-01.md §SemVer
 *                 Automation Plan → Tag-naming convention).
 *
 * @immutable
 */
final class MonorepoPackage
{
    /**
     * @param string      $name              Logical identifier "tier/short-name"
     * @param string      $path              Repo-relative directory path
     * @param string      $tagPrefix         Tag prefix without the trailing "-v"
     * @param string|null $legacyTagPattern  PCRE regex matching legacy tag forms
     */
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly string $tagPrefix,
        public readonly ?string $legacyTagPattern = null,
    ) {
    }

    /**
     * Discover all library packages under tier directories (Core/, Hub/, Bridge/, Spokes/*/)
     * or legacy packages/ directory.
     *
     * Skips entries whose composer.json declares "type": "project" — those
     * are not library packages and do not get SemVer tags via the loom.
     *
     * Since the architecture migration, packages live directly in tier directories
     * (Core/Container, Hub/Config, etc.) without per-component composer.json files.
     * Discovery falls back to detecting component directories by presence of
     * phpunit.xml.dist or a component manifest.
     *
     * @param string $repoRoot Absolute path to the monorepo root.
     * @return array<int, self>
     */
    public static function discover(string $repoRoot): array
    {
        $packages = [];

        // First, try the new tier directory structure (post-migration)
        // Tier directories: Core/, Hub/, Bridge/, Spokes/Internal/, Spokes/External/
        $tierDirs = ['Core', 'Hub', 'Bridge', 'Spokes/Internal', 'Spokes/External'];
        foreach ($tierDirs as $tierDir) {
            $fullTierDir = rtrim($repoRoot, '/') . '/' . $tierDir;
            if (!is_dir($fullTierDir)) {
                continue;
            }
            $tierName = strtolower(basename($tierDir)); // core, hub, bridge, internal, external
            if ($tierName === 'internal') {
                $tierName = 'spoke-internal';
            } elseif ($tierName === 'external') {
                $tierName = 'spoke-external';
            }
            
            $entries = scandir($fullTierDir);
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $componentDir = $fullTierDir . '/' . $entry;
                if (!is_dir($componentDir)) {
                    continue;
                }
                // Check if this is a valid component (has phpunit.xml.dist or similar marker)
                if (!file_exists($componentDir . '/phpunit.xml.dist')) {
                    continue;
                }
                $shortName = $entry;
                $fullName = "{$tierName}/{$shortName}";
                $tagPrefix = "{$tierName}-{$shortName}";
                $relativePath = $tierDir . '/' . $shortName;
                
                // Grandfathered: core/container's inaugural tag is unprefixed "v1.0.0".
                $legacy = ($fullName === 'core/container')
                    ? '/^v(\d+\.\d+\.\d+)$/'
                    : null;

                $packages[] = new self($fullName, $relativePath, $tagPrefix, $legacy);
            }
        }

        // Fallback: legacy packages/ directory structure (pre-migration)
        $pattern = rtrim($repoRoot, '/') . '/packages/*/*/composer.json';
        $matches = glob($pattern) ?: [];
        foreach ($matches as $composerPath) {
            $contents = file_get_contents($composerPath);
            if ($contents === false) {
                continue;
            }
            $json = json_decode($contents, true);
            if (!is_array($json)) {
                continue;
            }
            // Skip type:project packages — they follow their own release cadence.
            if (($json['type'] ?? 'library') !== 'library') {
                continue;
            }

            $packageDir = dirname($composerPath);             // .../packages/core/container
            $shortName  = basename($packageDir);              // container
            $tierName   = basename(dirname($packageDir));     // core
            $fullName   = "{$tierName}/{$shortName}";         // core/container
            $tagPrefix  = "{$tierName}-{$shortName}";         // core-container

            // Grandfathered: core/container's inaugural tag is unprefixed "v1.0.0".
            // Future container releases MUST use the prefixed form.
            $legacy = ($fullName === 'core/container')
                ? '/^v(\d+\.\d+\.\d+)$/'
                : null;

            // Store the path relative to the repo root (more portable than absolute).
            $relativePath = 'packages/' . $tierName . '/' . $shortName;

            // Avoid duplicates if already discovered from new structure
            $exists = false;
            foreach ($packages as $p) {
                if ($p->name === $fullName) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $packages[] = new self($fullName, $relativePath, $tagPrefix, $legacy);
            }
        }

        usort($packages, static fn (self $a, self $b): int => strcmp($a->name, $b->name));
        return $packages;
    }

    /**
     * Find a single package by its logical name (e.g. "core/container").
     *
     * @param string $repoRoot Absolute path to the monorepo root.
     * @param string $name     Logical package name "tier/short-name".
     */
    public static function find(string $repoRoot, string $name): ?self
    {
        foreach (self::discover($repoRoot) as $package) {
            if ($package->name === $name) {
                return $package;
            }
        }
        return null;
    }

    /**
     * Construct a configured RepoManager for this package.
     *
     * The RepoManager is given the package's tagPrefix and pathScope, plus
     * any legacy tag pattern (so core/container can still read its
     * grandfathered v1.0.0 tag).
     *
     * @param string $repoRoot Absolute path to the monorepo root (where .git lives).
     */
    public function createRepoManager(string $repoRoot): RepoManager
    {
        $manager = new RepoManager($repoRoot, $this->tagPrefix, $this->path);
        if ($this->legacyTagPattern !== null) {
            $manager->setAdditionalTagPatterns([$this->legacyTagPattern]);
        }
        return $manager;
    }
}
