<?php

declare(strict_types=1);

namespace SovereignStack\Orchestrator\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Orchestrator\VersionBumpEngine;

final class VersionBumpEngineTest extends TestCase
{
    private VersionBumpEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new VersionBumpEngine();
    }

    /**
     * @return array<int, array{message: string, expected: array{type: string, scope: string, description: string, breaking: bool}}>
     */
    public static function parseCommitProvider(): array
    {
        return [
            'simple fix' => [
                'message' => 'fix: resolve login issue',
                'expected' => ['type' => 'fix', 'scope' => '', 'description' => 'resolve login issue', 'breaking' => false],
            ],
            'feature with scope' => [
                'message' => 'feat(api): add user endpoint',
                'expected' => ['type' => 'feat', 'scope' => 'api', 'description' => 'add user endpoint', 'breaking' => false],
            ],
            'breaking change with bang' => [
                'message' => 'feat!: redesign auth system',
                'expected' => ['type' => 'feat', 'scope' => '', 'description' => 'redesign auth system', 'breaking' => true],
            ],
            'breaking change with scope and bang' => [
                'message' => 'feat(core)!: change database schema',
                'expected' => ['type' => 'feat', 'scope' => 'core', 'description' => 'change database schema', 'breaking' => true],
            ],
            'non-conventional commit' => [
                'message' => 'some random message',
                'expected' => ['type' => 'unknown', 'scope' => '', 'description' => 'some random message', 'breaking' => false],
            ],
            'chore without scope' => [
                'message' => 'chore: update dependencies',
                'expected' => ['type' => 'chore', 'scope' => '', 'description' => 'update dependencies', 'breaking' => false],
            ],
            'multi-line with breaking change body' => [
                'message' => "feat: add new feature\n\nBREAKING CHANGE: this changes the API",
                'expected' => ['type' => 'feat', 'scope' => '', 'description' => 'add new feature', 'breaking' => true],
            ],
        ];
    }

    /**
     * @param array{type: string, scope: string, description: string, breaking: bool} $expected
     * @dataProvider parseCommitProvider
     */
    public function testParseCommit(string $message, array $expected): void
    {
        $result = $this->engine->parseCommit($message);
        self::assertSame($expected, $result);
    }

    public function testAnalyzeMajor(): void
    {
        $commits = [
            'feat!: redesign auth system',
            'fix: small bug fix',
        ];

        $result = $this->engine->analyze($commits);

        self::assertSame('major', $result['increment']);
        self::assertStringContainsString('Breaking', $result['reason']);
    }

    public function testAnalyzeMajorFromBreakingChangeBody(): void
    {
        $commits = [
            "feat: add new feature\n\nBREAKING CHANGE: API changed",
        ];

        $result = $this->engine->analyze($commits);

        self::assertSame('major', $result['increment']);
    }

    public function testAnalyzeMinor(): void
    {
        $commits = [
            'feat(api): add user endpoint',
            'fix: resolve login issue',
            'chore: update deps',
        ];

        $result = $this->engine->analyze($commits);

        self::assertSame('minor', $result['increment']);
        self::assertStringContainsString('feature', $result['reason']);
    }

    public function testAnalyzePatch(): void
    {
        $commits = [
            'fix: resolve login issue',
            'perf: optimize query',
            'docs: update readme',
        ];

        $result = $this->engine->analyze($commits);

        self::assertSame('patch', $result['increment']);
    }

    public function testAnalyzeRefactor(): void
    {
        $commits = [
            'refactor: extract service class',
        ];

        $result = $this->engine->analyze($commits);

        self::assertSame('patch', $result['increment']);
    }

    public function testAnalyzeMergeCommitsAreIgnored(): void
    {
        $commits = [
            'Merge branch feature/xyz',
            'fix: resolve login issue',
        ];

        $result = $this->engine->analyze($commits);

        self::assertSame('patch', $result['increment']);
    }

    public function testCalculateNewVersion(): void
    {
        // 4-segment ADR-019 scheme per VersionBumpEngine fix (Task 46).
        // Pre-fix: these used 3-segment '1.5.3' inputs and outputs; the engine
        // would throw on real package versions like '0.1.0.0'.
        self::assertSame('2.0.0.0', $this->engine->calculateNewVersion('1.5.3.0', 'major'));
        self::assertSame('1.6.0.0', $this->engine->calculateNewVersion('1.5.3.0', 'minor'));
        self::assertSame('1.5.4.0', $this->engine->calculateNewVersion('1.5.3.0', 'patch'));
    }

    public function testCalculateNewVersionMajorResetsMinorAndPatch(): void
    {
        // 4-segment: major bump resets segments 2/3/4.
        self::assertSame('2.0.0.0', $this->engine->calculateNewVersion('1.9.9.0', 'major'));
        // Also verify a non-zero 4th segment gets reset.
        self::assertSame('2.0.0.0', $this->engine->calculateNewVersion('1.9.9.5', 'major'));
    }

    public function testCalculateNewVersionMinorResetsPatch(): void
    {
        // 4-segment: minor bump resets segments 3 (Lap) and 4 (Patch).
        self::assertSame('1.10.0.0', $this->engine->calculateNewVersion('1.9.9.0', 'minor'));
        // Also verify a non-zero 4th segment gets reset.
        self::assertSame('1.10.0.0', $this->engine->calculateNewVersion('1.9.9.5', 'minor'));
    }

    public function testCalculateNewVersionInvalidIncrement(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid increment');
        $this->engine->calculateNewVersion('1.0.0.0', 'invalid');
    }

    public function testCalculateNewVersionInvalidFormat(): void
    {
        // 'v1.0.0.0' is rejected because the regex expects bare digits
        // (no 'v' prefix).
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid SemVer');
        $this->engine->calculateNewVersion('v1.0.0.0', 'patch');
    }

    /**
     * Regression guard for the Task 46 fix: 3-segment versions MUST be rejected
     * because every package's composer.json carries a 4-segment ADR-019 version.
     * Before the fix, the engine accepted 3-segment versions, which silently
     * mismatched the 4-segment package versions and caused per-tier releases
     * to no-op forever.
     */
    public function testCalculateNewVersionRejectsThreeSegmentFormat(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid SemVer format: 1.5.3');
        $this->engine->calculateNewVersion('1.5.3', 'patch');
    }

    /**
     * Verifies the engine handles real package versions (all Step-5 packages
     * ship with "0.1.0.0" in their composer.json).
     */
    public function testCalculateNewVersionHandlesRealPackageVersionZeroOneZeroZero(): void
    {
        // First major bump on a prerelease package → MUWV flip from 0 to 1.
        self::assertSame('1.0.0.0', $this->engine->calculateNewVersion('0.1.0.0', 'major'));
        // First minor bump → new milestone for the tier.
        self::assertSame('0.2.0.0', $this->engine->calculateNewVersion('0.1.0.0', 'minor'));
        // First patch bump → first lap for the tier (this is what fix:/refactor:
        // commits produce).
        self::assertSame('0.1.1.0', $this->engine->calculateNewVersion('0.1.0.0', 'patch'));
    }

    /**
     * Verifies the Patch segment (4th) is never bumped by conventional commits
     * — it's reserved for emergency patches via a separate path.
     */
    public function testCalculateNewVersionPatchSegmentAlwaysResetsToZero(): void
    {
        // Patch increment bumps Lap (segment 3) and resets Patch (segment 4).
        self::assertSame('0.1.2.0', $this->engine->calculateNewVersion('0.1.1.5', 'patch'));
        // Minor increment bumps Milestone (segment 2) and resets Lap+Patch.
        self::assertSame('0.2.0.0', $this->engine->calculateNewVersion('0.1.1.5', 'minor'));
        // Major increment bumps MUWV (segment 1) and resets Milestone/Lap/Patch.
        self::assertSame('1.0.0.0', $this->engine->calculateNewVersion('0.1.1.5', 'major'));
    }

    public function testEmptyCommitList(): void
    {
        $result = $this->engine->analyze([]);

        self::assertSame('none', $result['increment']);
        self::assertStringContainsString('No recognized', $result['reason']);
    }
}
