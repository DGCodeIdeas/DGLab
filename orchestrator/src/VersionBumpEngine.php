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
        // Per ADR-019, package versions use the 4-segment scheme:
        //   <MUWV>.<Milestone>.<Lap>.<Patch>
        // MUWV (segment 1)       = master stability flag (0 = prerelease, 1 = stable).
        // Milestone (segment 2)  = milestone number.
        // Lap (segment 3)        = release lap within the milestone.
        // Patch (segment 4)     = emergency patch within a lap (not bumped by
        //                          conventional commits — reserved for manual
        //                          emergency fixes via a separate path).
        //
        // Conventional-commit increment → 4-segment bump mapping:
        //   'major' (breaking change) → bump MUWV,        reset Milestone/Lap/Patch
        //   'minor' (new feature)    → bump Milestone,   reset Lap/Patch
        //   'patch' (fix/refactor)   → bump Lap,         reset Patch
        //
        // This mapping preserves the standard SemVer semantic ordering
        // (major > minor > patch) while keeping the 4-segment ADR-019 layout
        // consistent across monorepo and per-tier tags.
        //
        // Before this fix, the regex was strict 3-segment `^(\d+)\.(\d+)\.(\d+)$`
        // and every package's composer.json had `"version": "0.1.0.0"` (4 segments).
        // The regex didn't match, `calculateNewVersion` threw, the workflow's
        // per-package loop swallowed the throw (`|| continue`), `has_bump` stayed
        // `false`, and per-tier tags were never created. Silent no-op for the
        // entire Step-5 release cycle. See worklog Task 46 for the trace.
        if (!\preg_match('/^(\d+)\.(\d+)\.(\d+)\.(\d+)$/', $currentVersion, $matches)) {
            throw new \RuntimeException("Invalid SemVer format: {$currentVersion}");
        }

        $muwv = (int) $matches[1];
        $milestone = (int) $matches[2];
        $lap = (int) $matches[3];
        // Patch segment (matches[4]) is intentionally not assigned — it is
        // never bumped by conventional commits (reserved for emergency patches
        // via a separate path; see ADR-019 §4).

        return match ($increment) {
            'major' => \sprintf('%d.%d.%d.%d', $muwv + 1, 0, 0, 0),
            'minor' => \sprintf('%d.%d.%d.%d', $muwv, $milestone + 1, 0, 0),
            'patch' => \sprintf('%d.%d.%d.%d', $muwv, $milestone, $lap + 1, 0),
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
