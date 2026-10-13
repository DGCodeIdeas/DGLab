<?php

declare(strict_types=1);

namespace SovereignStack\Orchestrator\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Black-box contract tests for `loom version:bump` (SAAI remediation Phase 3).
 *
 * The release workflow consumes loom's JSON across a shell boundary, so the
 * contract is pinned at the exact surface the workflow calls — not on internal
 * refactors the workflow never touches:
 *  - an unresolvable package name must exit non-zero with an explanatory
 *    message (never a vacuous increment "none" that CI would skip green);
 *  - a resolvable monorepo package must exit 0 with strict JSON carrying the
 *    documented keys.
 */
final class LoomVersionBumpContractTest extends TestCase
{
    private const JSON_KEYS = [
        'package', 'path', 'tag_prefix', 'current_version',
        'new_version', 'increment', 'reason', 'commits_analyzed',
    ];

    public function testUnresolvablePackageExitsNonZeroWithMessage(): void
    {
        [$exit, $out, $err] = $this->runLoom('version:bump', 'nope/nonexistent', '--format=json');

        self::assertNotSame(0, $exit, 'an unknown package must not exit 0');
        self::assertStringContainsString('Unknown package', $err, 'the error must be explained on STDERR');
        self::assertStringNotContainsString('"increment"', $out, 'failure must not emit bump JSON');
    }

    public function testMonorepoPackageEmitsParseableBumpJson(): void
    {
        [$exit, $out] = $this->runLoom('version:bump', 'core/logger', '--format=json');

        self::assertSame(0, $exit, "loom exited non-zero:\n{$out}");
        $decoded = \json_decode($out, true);
        self::assertIsArray($decoded, 'stdout must be JSON when --format=json');

        foreach (self::JSON_KEYS as $key) {
            self::assertArrayHasKey($key, $decoded, "documented key '{$key}' missing from loom JSON");
        }

        self::assertContains($decoded['increment'], ['major', 'minor', 'patch', 'none']);
        self::assertIsInt($decoded['commits_analyzed']);
    }

    /**
     * @return array{0: int, 1: string, 2: string} exit code, stdout, stderr
     */
    private function runLoom(string ...$args): array
    {
        $repoRoot = \dirname(__DIR__, 2);
        $command = \array_merge([\PHP_BINARY, $repoRoot . '/orchestrator/bin/loom'], $args);

        $process = \proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $repoRoot);
        self::assertIsResource($process, 'failed to spawn the loom binary');

        $stdout = (string) \stream_get_contents($pipes[1]);
        $stderr = (string) \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $exit = \proc_close($process);

        return [$exit, $stdout, $stderr];
    }
}
