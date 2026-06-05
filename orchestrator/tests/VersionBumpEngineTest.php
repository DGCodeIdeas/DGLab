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
        self::assertSame('2.0.0', $this->engine->calculateNewVersion('1.5.3', 'major'));
        self::assertSame('1.6.0', $this->engine->calculateNewVersion('1.5.3', 'minor'));
        self::assertSame('1.5.4', $this->engine->calculateNewVersion('1.5.3', 'patch'));
    }

    public function testCalculateNewVersionMajorResetsMinorAndPatch(): void
    {
        self::assertSame('2.0.0', $this->engine->calculateNewVersion('1.9.9', 'major'));
    }

    public function testCalculateNewVersionMinorResetsPatch(): void
    {
        self::assertSame('1.10.0', $this->engine->calculateNewVersion('1.9.9', 'minor'));
    }

    public function testCalculateNewVersionInvalidIncrement(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid increment');
        $this->engine->calculateNewVersion('1.0.0', 'invalid');
    }

    public function testCalculateNewVersionInvalidFormat(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid SemVer');
        $this->engine->calculateNewVersion('v1.0.0', 'patch');
    }

    public function testEmptyCommitList(): void
    {
        $result = $this->engine->analyze([]);

        self::assertSame('patch', $result['increment']);
        self::assertStringContainsString('defaulting', $result['reason']);
    }
}
