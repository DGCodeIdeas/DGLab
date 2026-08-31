<?php

declare(strict_types=1);

namespace SovereignStack\Orchestrator;

class VersionBumpEngine
{
    /**
     * @param array<int, string> $commitMessages
     * @return array{increment: string, reason: string}
     */
    public function analyze(array $commitMessages): array
    {
        $hasBreaking = false;
        $hasFeature = false;
        $hasPatch = false;

        foreach ($commitMessages as $message) {
            // Skip merge commits
            if (\str_starts_with($message, 'Merge')) {
                continue;
            }

            $parsed = $this->parseCommit($message);

            if ($parsed['breaking']) {
                $hasBreaking = true;
            }

            // Also check body for BREAKING CHANGE:
            if (\preg_match('/BREAKING CHANGE:/', $message)) {
                $hasBreaking = true;
            }

            if ($parsed['type'] === 'feat' && !$parsed['breaking']) {
                $hasFeature = true;
            }

            if (\in_array($parsed['type'], ['fix', 'perf', 'refactor', 'docs', 'style', 'test', 'chore'], true)) {
                $hasPatch = true;
            }
        }

        if ($hasBreaking) {
            return [
                'increment' => 'major',
                'reason' => 'Breaking change detected in commit messages.',
            ];
        }

        if ($hasFeature) {
            return [
                'increment' => 'minor',
                'reason' => 'New feature commit(s) detected.',
            ];
        }

        if ($hasPatch) {
            return [
                'increment' => 'patch',
                'reason' => 'Patch-level commit(s) detected.',
            ];
        }

        return [
            'increment' => 'patch',
            'reason' => 'No recognized commits; defaulting to patch increment.',
        ];
    }

    public function calculateNewVersion(string $currentVersion, string $increment): string
    {
        if (!\preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $currentVersion, $matches)) {
            throw new \RuntimeException("Invalid SemVer format: {$currentVersion}");
        }

        $major = (int) $matches[1];
        $minor = (int) $matches[2];
        $patch = (int) $matches[3];

        return match ($increment) {
            'major' => \sprintf('%d.%d.%d', $major + 1, 0, 0),
            'minor' => \sprintf('%d.%d.%d', $major, $minor + 1, 0),
            'patch' => \sprintf('%d.%d.%d', $major, $minor, $patch + 1),
            default => throw new \RuntimeException("Invalid increment type: {$increment}"),
        };
    }

    /**
     * @return array{type: string, scope: string, description: string, breaking: bool}
     */
    public function parseCommit(string $message): array
    {
        // Get the first line of the commit message (subject)
        $subject = $message;
        $firstNewline = \strpos($message, "\n");
        if ($firstNewline !== false) {
            $subject = \substr($message, 0, $firstNewline);
        }

        $pattern = '/^(?P<type>\w+)(?:\((?P<scope>[^)]+)\))?(?P<breaking>!)?\s*:\s*(?P<description>.+)$/';

        if (!\preg_match($pattern, $subject, $matches)) {
            return [
                'type' => 'unknown',
                'scope' => '',
                'description' => $subject,
                'breaking' => false,
            ];
        }

        $breaking = $matches['breaking'] === '!' || \preg_match('/BREAKING CHANGE:/', $message);

        return [
            'type' => $matches['type'],
            'scope' => $matches['scope'],
            'description' => $matches['description'],
            'breaking' => $breaking,
        ];
    }
}
