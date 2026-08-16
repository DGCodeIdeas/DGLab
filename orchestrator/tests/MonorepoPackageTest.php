<?php

declare(strict_types=1);

namespace SovereignStack\Orchestrator\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Orchestrator\MonorepoPackage;

final class MonorepoPackageTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = \sys_get_temp_dir() . '/loom_mono_pkg_test_' . \bin2hex(\random_bytes(4));
        // Create a minimal monorepo layout:
        //   <root>/packages/core/container/composer.json       (library)
        //   <root>/packages/core/event-dispatcher/composer.json (library)
        //   <root>/packages/core/some-cli/composer.json        (project — should be skipped)
        \mkdir($this->tempRoot . '/packages/core/container', 0777, true);
        \mkdir($this->tempRoot . '/packages/core/event-dispatcher', 0777, true);
        \mkdir($this->tempRoot . '/packages/core/some-cli', 0777, true);

        \file_put_contents(
            $this->tempRoot . '/packages/core/container/composer.json',
            \json_encode([
                'name' => 'sovereign-stack/core-container',
                'type' => 'library',
                'license' => 'MIT',
            ], JSON_PRETTY_PRINT),
        );

        \file_put_contents(
            $this->tempRoot . '/packages/core/event-dispatcher/composer.json',
            \json_encode([
                'name' => 'sovereign-stack/core-event-dispatcher',
                'type' => 'library',
                'license' => 'MIT',
            ], JSON_PRETTY_PRINT),
        );

        \file_put_contents(
            $this->tempRoot . '/packages/core/some-cli/composer.json',
            \json_encode([
                'name' => 'sovereign-stack/some-cli',
                'type' => 'project',
                'license' => 'MIT',
            ], JSON_PRETTY_PRINT),
        );
    }

    protected function tearDown(): void
    {
        $this->rmDir($this->tempRoot);
    }

    public function testDiscoverFindsAllLibraryPackages(): void
    {
        $packages = MonorepoPackage::discover($this->tempRoot);

        // Two library packages; the project package MUST be skipped.
        self::assertCount(2, $packages);

        $names = \array_map(static fn (MonorepoPackage $p): string => $p->name, $packages);
        sort($names);
        self::assertSame(['core/container', 'core/event-dispatcher'], $names);
    }

    public function testDiscoverSkipsProjectPackages(): void
    {
        $packages = MonorepoPackage::discover($this->tempRoot);

        foreach ($packages as $package) {
            self::assertNotSame('core/some-cli', $package->name);
        }
    }

    public function testDiscoverReturnsSortedByName(): void
    {
        $packages = MonorepoPackage::discover($this->tempRoot);
        $names = \array_map(static fn (MonorepoPackage $p): string => $p->name, $packages);

        $sorted = $names;
        sort($sorted);
        self::assertSame($sorted, $names);
    }

    public function testContainerPackageHasGrandfatheredLegacyPattern(): void
    {
        $package = MonorepoPackage::find($this->tempRoot, 'core/container');
        self::assertNotNull($package);
        self::assertSame('core-container', $package->tagPrefix);
        self::assertSame('/^v(\d+\.\d+\.\d+)$/', $package->legacyTagPattern);
        self::assertSame('packages/core/container', $package->path);
    }

    public function testEventDispatcherPackageHasNoLegacyPattern(): void
    {
        $package = MonorepoPackage::find($this->tempRoot, 'core/event-dispatcher');
        self::assertNotNull($package);
        self::assertSame('core-event-dispatcher', $package->tagPrefix);
        self::assertNull($package->legacyTagPattern);
        self::assertSame('packages/core/event-dispatcher', $package->path);
    }

    public function testFindReturnsNullForUnknownPackage(): void
    {
        $package = MonorepoPackage::find($this->tempRoot, 'core/nonexistent');
        self::assertNull($package);
    }

    public function testCreateRepoManagerForContainerHasPrefixAndLegacyPattern(): void
    {
        $package = MonorepoPackage::find($this->tempRoot, 'core/container');
        self::assertNotNull($package);
        $manager = $package->createRepoManager($this->tempRoot);

        self::assertSame('core-container', $manager->getTagPrefix());
        self::assertSame('packages/core/container', $manager->getPathScope());
    }

    public function testCreateRepoManagerForEventDispatcherHasNoPathScopeFromLegacy(): void
    {
        $package = MonorepoPackage::find($this->tempRoot, 'core/event-dispatcher');
        self::assertNotNull($package);
        $manager = $package->createRepoManager($this->tempRoot);

        self::assertSame('core-event-dispatcher', $manager->getTagPrefix());
        self::assertSame('packages/core/event-dispatcher', $manager->getPathScope());
    }

    public function testDiscoverWithEmptyRepoRootReturnsEmptyArray(): void
    {
        $emptyRoot = \sys_get_temp_dir() . '/loom_empty_' . \bin2hex(\random_bytes(4));
        \mkdir($emptyRoot, 0777, true);
        try {
            $packages = MonorepoPackage::discover($emptyRoot);
            self::assertSame([], $packages);
        } finally {
            @\rmdir($emptyRoot);
        }
    }

    /**
     * Recursively remove a directory.
     */
    private function rmDir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @\rmdir($item->getRealPath());
            } else {
                @\unlink($item->getRealPath());
            }
        }

        @\rmdir($dir);
    }
}
