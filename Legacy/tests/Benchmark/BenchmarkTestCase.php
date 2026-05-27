<?php

namespace DGLab\Tests\Benchmark;

use DGLab\Tests\TestCase;

abstract class BenchmarkTestCase extends TestCase
{
    protected function benchmark(string $name, callable $callback, int $iterations = 100): void
    {
        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = hrtime(true);
            $callback();
            $end = hrtime(true);
            $times[] = ($end - $start) / 1000000; // ms
        }

        $avg = array_sum($times) / count($times);
        $min = min($times);
        $max = max($times);

        echo "\nBenchmark [$name]:\n";
        echo "  Iterations: $iterations\n";
        echo "  Average:    " . number_format($avg, 4) . " ms\n";
        echo "  Min:        " . number_format($min, 4) . " ms\n";
        echo "  Max:        " . number_format($max, 4) . " ms\n";
    }
}
