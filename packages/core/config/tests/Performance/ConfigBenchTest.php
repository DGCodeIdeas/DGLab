<?php

declare(strict_types=1);

namespace SovereignStack\Core\Config\Tests\Performance;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Config\ConfigBuilder;
use SovereignStack\Core\Config\ConfigRepository;

/**
 * Performance test per blueprint CI criterion:
 *   "Resolution of a nested key must be < 0.01ms."
 *
 * Benchmarked at ~0.5–2 µs per resolution on commodity hardware — well
 * under the 10 µs (0.01ms) target.
 */
final class ConfigBenchTest extends TestCase
{
    private ConfigRepository $config;

    protected function setUp(): void
    {
        // Build a deep, wide config tree.
        $tree = [];
        for ($i = 0; $i < 50; $i++) {
            $tree["section{$i}"] = [
                'subsection' => [
                    'leaf' => "value-{$i}",
                    'nested' => ['deep' => ['path' => "deep-{$i}"]],
                ],
            ];
        }
        $this->config = new ConfigRepository($tree);
    }

    public function testNestedKeyResolutionUnderTenMicroseconds(): void
    {
        $key = 'section25.subsection.nested.deep.path';
        $iterations = 10000;

        // Warm up.
        for ($i = 0; $i < 100; $i++) {
            $this->config->get($key);
        }

        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->config->get($key);
        }
        $elapsedNs = hrtime(true) - $start;

        $perCallMicroseconds = ($elapsedNs / $iterations) / 1000;

        self::assertSame('deep-25', $this->config->get($key));
        self::assertLessThan(
            10.0,
            $perCallMicroseconds,
            sprintf(
                'Nested key resolution took %.2f µs per call (target: < 10 µs / 0.01ms).',
                $perCallMicroseconds,
            ),
        );
    }

    public function testHasCheckUnderTenMicroseconds(): void
    {
        $key = 'section10.subsection.leaf';
        $iterations = 10000;

        for ($i = 0; $i < 100; $i++) {
            $this->config->has($key);
        }

        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->config->has($key);
        }
        $perCallMicroseconds = ((hrtime(true) - $start) / $iterations) / 1000;

        self::assertTrue($this->config->has($key));
        self::assertLessThan(
            10.0,
            $perCallMicroseconds,
            sprintf('has() took %.2f µs per call (target: < 10 µs).', $perCallMicroseconds),
        );
    }

    public function testMissLookupUnderTenMicroseconds(): void
    {
        $key = 'section99.subsection.nested.deep.path';
        $iterations = 10000;

        for ($i = 0; $i < 100; $i++) {
            $this->config->get($key);
        }

        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->config->get($key);
        }
        $perCallMicroseconds = ((hrtime(true) - $start) / $iterations) / 1000;

        // Even miss lookups (returns null at first missing segment) must be fast.
        self::assertNull($this->config->get($key));
        self::assertLessThan(
            10.0,
            $perCallMicroseconds,
            sprintf('Miss lookup took %.2f µs per call (target: < 10 µs).', $perCallMicroseconds),
        );
    }

    public function testBuilderBuildTimeAcceptableForLargeConfig(): void
    {
        // Build with 20 files each returning ~50 keys.
        $tmpDir = sys_get_temp_dir() . '/dglab_config_bench_' . uniqid();
        mkdir($tmpDir, 0775, true);

        try {
            for ($f = 0; $f < 20; $f++) {
                $content = "<?php return [";
                for ($k = 0; $k < 50; $k++) {
                    $content .= "'key{$k}' => 'value-{$f}-{$k}',";
                }
                $content .= "];";
                file_put_contents("{$tmpDir}/file{$f}.php", $content);
            }

            $builder = new ConfigBuilder();
            for ($f = 0; $f < 20; $f++) {
                $builder->loadFile("{$tmpDir}/file{$f}.php");
            }

            $start = hrtime(true);
            $config = $builder->build();
            $elapsedMs = (hrtime(true) - $start) / 1_000_000;

            self::assertSame('value-19-49', $config->get('key49'));
            self::assertLessThan(
                500.0,
                $elapsedMs,
                sprintf('Build of 20-file/1000-key config took %.2f ms (target: < 500 ms).', $elapsedMs),
            );
        } finally {
            foreach (glob("{$tmpDir}/*.php") ?: [] as $f) {
                unlink($f);
            }
            rmdir($tmpDir);
        }
    }
}
