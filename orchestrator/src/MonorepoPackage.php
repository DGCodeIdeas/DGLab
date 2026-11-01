<?php

declare(strict_types=1);

namespace SovereignStack\Orchestrator;

final class MonorepoPackage
{
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly string $tagPrefix,
        public readonly ?string $legacyTagPattern = null,
    ) {
    }

    public static function discover(string $repoRoot): array
    {
        $packages = [];

        $tierDirs = ['Core', 'Hub', 'Bridge', 'Spokes/Internal', 'Spokes/External'];
        foreach ($tierDirs as $tierDir) {
            $fullTierDir = rtrim($repoRoot, '/') . '/' . $tierDir;
            if (!is_dir($fullTierDir)) {
                continue;
            }
            $tierName = strtolower(basename($tierDir));
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
                if (!file_exists($componentDir . '/phpunit.xml.dist')) {
                    continue;
                }
                $shortName = strtolower($entry);
                $fullName = "{$tierName}/{$shortName}";
                $tagPrefix = "{$tierName}-{$shortName}";
                $relativePath = $tierDir . '/' . $entry;

                $legacy = ($fullName === 'core/container')
                    ? '/^v(\d+\.\d+\.\d+)$/'
                    : null;

                $packages[] = new self($fullName, $relativePath, $tagPrefix, $legacy);
            }
        }

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
            if (($json['type'] ?? 'library') !== 'library') {
                continue;
            }

            $packageDir = dirname($composerPath);
            $shortName = basename($packageDir);
            $tierName = basename(dirname($packageDir));
            $fullName = "{$tierName}/{$shortName}";
            $tagPrefix = "{$tierName}-{$shortName}";

            $legacy = ($fullName === 'core/container')
                ? '/^v(\d+\.\d+\.\d+)$/'
                : null;

            $relativePath = 'packages/' . $tierName . '/' . $shortName;

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

    public static function find(string $repoRoot, string $name): ?self
    {
        foreach (self::discover($repoRoot) as $package) {
            if ($package->name === $name) {
                return $package;
            }
        }
        return null;
    }

    public function createRepoManager(string $repoRoot): RepoManager
    {
        $manager = new RepoManager($repoRoot, $this->tagPrefix, $this->path);
        if ($this->legacyTagPattern !== null) {
            $manager->setAdditionalTagPatterns([$this->legacyTagPattern]);
        }
        return $manager;
    }
}
