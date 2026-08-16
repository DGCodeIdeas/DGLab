<?php

declare(strict_types=1);

namespace SovereignStack\Orchestrator\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Orchestrator\Manifest;

/**
 * Tests for the Manifest class (P2 gap 10 — composer.json version-field sync).
 *
 * Verifies that setVersion() writes the version atomically, preserves other
 * fields, and rejects malformed input. The tests use temp files so they don't
 * touch the real packages/*/composer.json files.
 */
final class ManifestTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = \sys_get_temp_dir() . '/loom_manifest_' . \bin2hex(\random_bytes(4)) . '.json';
    }

    protected function tearDown(): void
    {
        if (\file_exists($this->tempFile)) {
            @\unlink($this->tempFile);
        }
        if (\file_exists($this->tempFile . '.loom-tmp')) {
            @\unlink($this->tempFile . '.loom-tmp');
        }
    }

    public function testSetVersionWritesField(): void
    {
        \file_put_contents($this->tempFile, '{"name": "test/pkg", "description": "x"}');
        Manifest::setVersion($this->tempFile, '1.2.3');

        $read = \json_decode((string) \file_get_contents($this->tempFile), true);
        self::assertSame('1.2.3', $read['version']);
    }

    public function testSetVersionPreservesOtherFields(): void
    {
        $original = [
            'name' => 'test/pkg',
            'description' => 'A test package',
            'type' => 'library',
            'require' => ['php' => '>=8.3'],
            'autoload' => ['psr-4' => ['Test\\Pkg\\' => 'src/']],
        ];
        \file_put_contents($this->tempFile, \json_encode($original, \JSON_PRETTY_PRINT));

        Manifest::setVersion($this->tempFile, '2.0.0');

        $read = \json_decode((string) \file_get_contents($this->tempFile), true);
        self::assertSame('test/pkg', $read['name']);
        self::assertSame('A test package', $read['description']);
        self::assertSame('library', $read['type']);
        self::assertSame('>=8.3', $read['require']['php']);
        self::assertSame('src/', $read['autoload']['psr-4']['Test\\Pkg\\']);
        self::assertSame('2.0.0', $read['version']);
    }

    public function testSetVersionOverwritesExistingVersion(): void
    {
        \file_put_contents($this->tempFile, '{"name": "test/pkg", "version": "1.0.0"}');
        Manifest::setVersion($this->tempFile, '1.1.0');

        $read = \json_decode((string) \file_get_contents($this->tempFile), true);
        self::assertSame('1.1.0', $read['version']);
    }

    public function testSetVersionRejectsNonSemVer(): void
    {
        \file_put_contents($this->tempFile, '{"name": "test/pkg"}');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid SemVer');
        Manifest::setVersion($this->tempFile, 'v1.0.0');
    }

    public function testSetVersionRejectsPrefixedVersion(): void
    {
        \file_put_contents($this->tempFile, '{"name": "test/pkg"}');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid SemVer');
        Manifest::setVersion($this->tempFile, 'core-pkg-v1.0.0');
    }

    public function testSetVersionFailsOnMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to read composer.json');
        Manifest::setVersion($this->tempFile . '.nonexistent', '1.0.0');
    }

    public function testSetVersionFailsOnInvalidJson(): void
    {
        \file_put_contents($this->tempFile, 'not json {{{');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not valid JSON');
        Manifest::setVersion($this->tempFile, '1.0.0');
    }

    public function testSetVersionDoesNotLeaveTempFile(): void
    {
        \file_put_contents($this->tempFile, '{"name": "test/pkg"}');
        Manifest::setVersion($this->tempFile, '1.0.0');

        // The .loom-tmp file should not exist after a successful write.
        self::assertFileDoesNotExist($this->tempFile . '.loom-tmp');
    }

    public function testSetVersionAppendsTrailingNewline(): void
    {
        \file_put_contents($this->tempFile, '{"name": "test/pkg"}');
        Manifest::setVersion($this->tempFile, '1.0.0');

        $contents = (string) \file_get_contents($this->tempFile);
        self::assertStringEndsWith("}\n", $contents);
    }

    public function testGetVersionReturnsField(): void
    {
        \file_put_contents($this->tempFile, '{"name": "test/pkg", "version": "1.5.0"}');
        self::assertSame('1.5.0', Manifest::getVersion($this->tempFile));
    }

    public function testGetVersionReturnsEmptyWhenAbsent(): void
    {
        \file_put_contents($this->tempFile, '{"name": "test/pkg"}');
        self::assertSame('', Manifest::getVersion($this->tempFile));
    }

    public function testGetVersionFailsOnMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to read composer.json');
        Manifest::getVersion($this->tempFile . '.nonexistent');
    }

    public function testGetVersionFailsOnInvalidJson(): void
    {
        \file_put_contents($this->tempFile, 'not json {{{');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not valid JSON');
        Manifest::getVersion($this->tempFile);
    }
}
