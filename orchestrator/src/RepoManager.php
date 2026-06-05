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

    public function __construct(?string $workingDir = null)
    {
        $this->git = new Git();
        $this->workingDir = $workingDir ?? \sys_get_temp_dir() . '/loom';
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

    public function tag(string $version, string $message = ''): bool
    {
        if (!\preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new \RuntimeException("Invalid SemVer tag format: {$version}");
        }

        $repo = $this->getRepository();

        try {
            $existingTags = $repo->getTags() ?? [];
        } catch (GitException $e) {
            throw new \RuntimeException('Failed to list tags: ' . $e->getMessage(), 0, $e);
        }

        if (\in_array($version, $existingTags, true)) {
            throw new \RuntimeException("Tag '{$version}' already exists and will not be overwritten.");
        }

        try {
            $options = $message !== '' ? ['-m' => $message] : ['-m' => $version];
            $repo->createTag($version, $options);
            return true;
        } catch (GitException $e) {
            throw new \RuntimeException('Git tag creation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getCurrentVersion(): string
    {
        $repo = $this->getRepository();

        try {
            $tags = $repo->getTags() ?? [];
        } catch (GitException $e) {
            throw new \RuntimeException('Failed to list tags: ' . $e->getMessage(), 0, $e);
        }

        $semverTags = \array_filter($tags, function (string $tag): bool {
            return (bool) \preg_match('/^\d+\.\d+\.\d+$/', $tag);
        });

        if ($semverTags === []) {
            return '0.0.1';
        }

        \usort($semverTags, function (string $a, string $b): int {
            return \version_compare($b, $a);
        });

        return $semverTags[0];
    }

    /**
     * @return array<int, string>
     */
    public function getLogSince(string $tag): array
    {
        $repo = $this->getRepository();

        try {
            $output = $repo->execute('log', "{$tag}..HEAD", '--format=%s');
        } catch (GitException $e) {
            throw new \RuntimeException('Failed to get log: ' . $e->getMessage(), 0, $e);
        }

        return $output;
    }

    public function getWorkingDir(): string
    {
        return $this->workingDir;
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
}
