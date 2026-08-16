<?php

declare(strict_types=1);

namespace SovereignStack\Orchestrator;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository;

class RepoManager
{
    private ?GitRepository $repository = null;

    private Git $git;

    private string $workingDir;

    /**
     * Tag-prefix for monorepo packages (e.g. "core-container").
     * When null, the manager operates in legacy mode: tag names are the bare
     * version string (e.g. "1.0.0"), matching the pre-monorepo behaviour.
     */
    private ?string $tagPrefix = null;

    /**
     * Optional path scope for getLogSince() — restricts git log to commits
     * touching this path. Essential for monorepos so a commit on package B
     * does not bump package A.
     */
    private ?string $pathScope = null;

    /**
     * Additional regex patterns for matching existing tags. Used to support
     * grandfathered tag-name forms (e.g. core/container's unprefixed v1.0.0).
     *
     * @var array<int, string>
     */
    private array $additionalTagPatterns = [];

    /**
     * @param string|null $workingDir Absolute path to the git working directory.
     * @param string|null $tagPrefix  Tag namespace prefix (e.g. "core-container")
     *                                — when set, tag() writes "{prefix}-v{version}"
     *                                and getCurrentVersion() filters by the same.
     * @param string|null $pathScope  Optional path scope (relative to workingDir)
     *                                passed to `git log -- <path>` so commits
     *                                outside the path are excluded.
     */
    public function __construct(?string $workingDir = null, ?string $tagPrefix = null, ?string $pathScope = null)
    {
        $this->git = new Git();
        $this->workingDir = $workingDir ?? \sys_get_temp_dir() . '/loom';
        $this->tagPrefix = $tagPrefix;
        $this->pathScope = $pathScope;
    }

    /**
     * Register additional regex patterns for matching existing tags.
     *
     * Each pattern MUST have a single capture group that yields the bare
     * version (e.g. "/^v(\d+\.\d+\.\d+)$/" yields "1.0.0" from "v1.0.0").
     *
     * @param array<int, string> $patterns
     */
    public function setAdditionalTagPatterns(array $patterns): void
    {
        $this->additionalTagPatterns = $patterns;
    }

    public function clone(string $url, string $path): bool
    {
        $fullPath = $this->workingDir . '/' . \ltrim($path, '/');

        try {
            $this->repository = $this->git->cloneRepository($url, $fullPath);
            return true;
        } catch (GitException $e) {
            throw new \RuntimeException('Git clone failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function checkout(string $branch): bool
    {
        $repo = $this->getRepository();

        try {
            // Try checking out an existing branch
            $repo->checkout($branch);
            return true;
        } catch (GitException $e) {
            // Branch may not exist locally; try creating it
            try {
                $repo->createBranch($branch, true);
                return true;
            } catch (GitException $e2) {
                throw new \RuntimeException('Git checkout failed: ' . $e2->getMessage(), 0, $e2);
            }
        }
    }

    /**
     * Create an annotated git tag for the given version.
     *
     * The tag NAME is derived from the version + tagPrefix:
     *   - tagPrefix set:    "{prefix}-v{version}" (e.g. "core-container-v1.1.0")
     *   - tagPrefix null:   "{version}"           (e.g. "1.0.0" — legacy mode)
     *
     * The version MUST match /^\d+\.\d+\.\d+$/ (bare SemVer, no prefix).
     */
    public function tag(string $version, string $message = ''): bool
    {
        if (!\preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new \RuntimeException("Invalid SemVer tag format: {$version}");
        }

        $tagName = $this->buildTagName($version);
        $repo = $this->getRepository();

        try {
            $existingTags = $repo->getTags() ?? [];
        } catch (GitException $e) {
            throw new \RuntimeException('Failed to list tags: ' . $e->getMessage(), 0, $e);
        }

        if (\in_array($tagName, $existingTags, true)) {
            throw new \RuntimeException("Tag '{$tagName}' already exists and will not be overwritten.");
        }

        try {
            $options = $message !== '' ? ['-m' => $message] : ['-m' => $tagName];
            $repo->createTag($tagName, $options);
            return true;
        } catch (GitException $e) {
            throw new \RuntimeException('Git tag creation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Push a previously-created tag to a remote.
     *
     * The remote URL is passed by the caller — for GitHub HTTPS remotes, the
     * caller is responsible for embedding the PAT via a one-shot URL of the
     * form "https://x-access-token:{token}@github.com/owner/repo.git" and
     * restoring the original remote URL afterwards. RepoManager NEVER mutates
     * git config to store credentials.
     *
     * For local file:// remotes (used in tests), pass the path directly.
     */
    public function pushTag(string $version, string $remoteUrl): bool
    {
        if (!\preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new \RuntimeException("Invalid SemVer format: {$version}");
        }

        $tagName = $this->buildTagName($version);
        $repo = $this->getRepository();

        try {
            // `git push <url> refs/tags/<tag>` — git accepts a URL in place of
            // a named remote. This avoids touching git config entirely.
            $repo->execute('push', $remoteUrl, 'refs/tags/' . $tagName);
            return true;
        } catch (GitException $e) {
            throw new \RuntimeException('Git tag push failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Assert that the working tree is clean — no modified tracked files and no
     * unexpected untracked files.
     *
     * @param array<int, string> $allowUntracked Untracked paths to tolerate
     *     (e.g. ["orchestrator/repos.json"] when the workflow generates that
     *     file at run start). Paths are matched against the porcelain v1
     *     output's filename portion (everything after the 2-char status + 1
     *     space). Both full paths and basenames are compared for robustness.
     * @throws \RuntimeException if the working tree is dirty.
     */
    public function assertClean(array $allowUntracked = []): void
    {
        $repo = $this->getRepository();

        try {
            /** @var array<int, string> $output */
            $output = $repo->execute('status', '--porcelain');
        } catch (GitException $e) {
            throw new \RuntimeException('Failed to check git status: ' . $e->getMessage(), 0, $e);
        }

        $modified = [];
        $unexpectedUntracked = [];

        foreach ($output as $line) {
            if ($line === '') {
                continue;
            }

            // Porcelain v1 format: "XY <path>" where XY is a 2-char status.
            // X = staged status, Y = working-tree status.
            // '  ' = unmodified (should not appear in --porcelain output).
            // '??' = untracked.
            // Anything else (' M', 'M ', 'MM', 'A ', 'D ', etc.) = modified.
            $status = \substr($line, 0, 2);
            $path = \ltrim(\substr($line, 2));

            if ($status === '??') {
                // Untracked — check the allowlist.
                // Compare both the full path and the basename for robustness.
                $basename = \basename($path);
                $allowed = false;
                foreach ($allowUntracked as $allowedPath) {
                    if ($path === $allowedPath || $basename === $allowedPath || $basename === \basename($allowedPath)) {
                        $allowed = true;
                        break;
                    }
                }
                if (!$allowed) {
                    $unexpectedUntracked[] = $line;
                }
            } elseif ($status !== '  ') {
                // Any status other than '??' and '  ' is a modification.
                $modified[] = $line;
            }
        }

        if ($modified !== []) {
            throw new \RuntimeException(
                'Working tree has modified tracked files:' . "\n" . \implode("\n", $modified)
            );
        }

        if ($unexpectedUntracked !== []) {
            throw new \RuntimeException(
                'Working tree has unexpected untracked files:' . "\n" . \implode("\n", $unexpectedUntracked)
            );
        }
    }

    /**
     * Return the highest SemVer version among this manager's tags.
     *
     * Tag-name matching uses the configured prefix:
     *   - tagPrefix set:    matches "{prefix}-v{X.Y.Z}"
     *   - tagPrefix null:   matches "{X.Y.Z}" or "v{X.Y.Z}" (legacy)
     *   - additionalTagPatterns: also matched (for grandfathered forms)
     *
     * Returns the bare version "X.Y.Z" (never the tag name).
     * Returns "0.0.1" if no matching tags exist.
     */
    public function getCurrentVersion(): string
    {
        $repo = $this->getRepository();

        try {
            $tags = $repo->getTags() ?? [];
        } catch (GitException $e) {
            throw new \RuntimeException('Failed to list tags: ' . $e->getMessage(), 0, $e);
        }

        $versions = [];
        $patterns = $this->buildTagPatterns();
        foreach ($tags as $tag) {
            foreach ($patterns as $pattern) {
                if (\preg_match($pattern, $tag, $m) && isset($m[1])) {
                    $versions[] = $m[1];
                    break;
                }
            }
        }

        if ($versions === []) {
            return '0.0.1';
        }

        \usort($versions, static fn (string $a, string $b): int => \version_compare($b, $a));
        return $versions[0];
    }

    /**
     * Return commit subjects since the given version's tag.
     *
     * If $pathScope is provided (or set in the constructor), restricts to
     * commits touching that path — essential for monorepos so a commit on
     * package B does not show up in package A's log.
     *
     * @param string|null $pathScope Optional path scope override.
     * @return array<int, string>
     */
    public function getLogSince(string $version, ?string $pathScope = null): array
    {
        $repo = $this->getRepository();
        $tagName = $this->findTagForVersion($version) ?? $this->buildTagName($version);
        $scope = $pathScope ?? $this->pathScope;

        $args = ['log', "{$tagName}..HEAD", '--format=%s'];
        if ($scope !== null) {
            $args[] = '--';
            $args[] = $scope;
        }

        try {
            /** @var array<int, string> $output */
            $output = $repo->execute(...$args);
        } catch (GitException $e) {
            throw new \RuntimeException('Failed to get log: ' . $e->getMessage(), 0, $e);
        }

        return $output;
    }

    public function getWorkingDir(): string
    {
        return $this->workingDir;
    }

    /**
     * Get the configured tag prefix (null in legacy mode).
     */
    public function getTagPrefix(): ?string
    {
        return $this->tagPrefix;
    }

    /**
     * Get the configured path scope (null = no path filter).
     */
    public function getPathScope(): ?string
    {
        return $this->pathScope;
    }

    private function getRepository(): GitRepository
    {
        if ($this->repository === null) {
            try {
                $this->repository = $this->git->open($this->workingDir);
            } catch (GitException $e) {
                throw new \RuntimeException('Failed to open git repository: ' . $e->getMessage(), 0, $e);
            }
        }

        return $this->repository;
    }

    /**
     * Build the tag NAME for a given bare version.
     * Used by tag() and pushTag() to construct the full tag name.
     */
    private function buildTagName(string $version): string
    {
        if ($this->tagPrefix !== null) {
            return "{$this->tagPrefix}-v{$version}";
        }
        return $version;
    }

    /**
     * Build the list of regex patterns to match existing tags.
     *
     * Each pattern MUST have a single capture group yielding the bare version.
     *
     * @return array<int, string>
     */
    private function buildTagPatterns(): array
    {
        $patterns = [];
        if ($this->tagPrefix !== null) {
            $escaped = \preg_quote($this->tagPrefix, '/');
            $patterns[] = '/^' . $escaped . '-v(\d+\.\d+\.\d+)$/';
        } else {
            // Legacy mode: accept bare "1.0.0" (existing tests) and "v1.0.0".
            $patterns[] = '/^v?(\d+\.\d+\.\d+)$/';
        }
        foreach ($this->additionalTagPatterns as $p) {
            $patterns[] = $p;
        }
        return $patterns;
    }

    /**
     * Find the actual tag name in the repo that corresponds to a version.
     * Returns null if no existing tag matches.
     */
    private function findTagForVersion(string $version): ?string
    {
        try {
            $tags = $this->getRepository()->getTags() ?? [];
        } catch (GitException) {
            return null;
        }
        $patterns = $this->buildTagPatterns();
        foreach ($tags as $tag) {
            foreach ($patterns as $pattern) {
                if (\preg_match($pattern, $tag, $m) && isset($m[1]) && $m[1] === $version) {
                    return $tag;
                }
            }
        }
        return null;
    }
}
