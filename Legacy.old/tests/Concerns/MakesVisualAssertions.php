<?php

namespace DGLab\Tests\Concerns;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

trait MakesVisualAssertions
{
    protected string $baselineDir = __DIR__ . '/../Browser/Screenshots/Baselines';
    protected string $failureDir = __DIR__ . '/../Browser/Screenshots/Failures';

    protected function assertVisualMatch(string $identifier, float $threshold = 0.95): void
    {
        if (!is_dir($this->baselineDir)) {
            mkdir($this->baselineDir, 0777, true);
        }
        if (!is_dir($this->failureDir)) {
            mkdir($this->failureDir, 0777, true);
        }

        $baselinePath = "$this->baselineDir/{$identifier}.png";
        $currentPath = "$this->failureDir/{$identifier}_current.png";
        $diffPath = "$this->failureDir/{$identifier}_diff.png";

        $client = $this->getClient();
        $client->takeScreenshot($currentPath);

        if (!file_exists($baselinePath)) {
            copy($currentPath, $baselinePath);
            $this->markTestIncomplete("Baseline screenshot created for '{$identifier}'. Please review and commit it.");
            return;
        }

        $manager = new ImageManager(new Driver());

        $baseline = $manager->read($baselinePath);
        $current = $manager->read($currentPath);

        if ($baseline->width() !== $current->width() || $baseline->height() !== $current->height()) {
             $this->fail("Visual match failed for '{$identifier}': Dimensions mismatch. Baseline: {$baseline->width()}x{$baseline->height()}, Current: {$current->width()}x{$current->height()}");
        }

        $similarity = $this->compareImages($baseline, $current, $diffPath);

        if ($similarity < $threshold) {
            $this->fail("Visual match failed for '{$identifier}': Similarity is " . round($similarity * 100, 2) . "%, expected at least " . ($threshold * 100) . "%. Diff saved at {$diffPath}");
        }

        // Clean up current if it matched
        @unlink($currentPath);
        @unlink($diffPath);
    }

    protected function compareImages($img1, $img2, string $diffPath): float
    {
        $width = $img1->width();
        $height = $img1->height();
        $totalPixels = $width * $height;
        $mismatchedPixels = 0;

        $step = 4;
        for ($x = 0; $x < $width; $x += $step) {
            for ($y = 0; $y < $height; $y += $step) {
                $p1 = $img1->pickColor($x, $y);
                $p2 = $img2->pickColor($x, $y);

                if ($p1->toHex() !== $p2->toHex()) {
                    $mismatchedPixels += ($step * $step);
                }
            }
        }

        $similarity = 1 - ($mismatchedPixels / $totalPixels);

        if ($similarity < 1.0) {
             copy($this->failureDir . '/' . basename($diffPath, '_diff.png') . '_current.png', $diffPath);
        }

        return $similarity;
    }

    protected function getClient()
    {
         if (property_exists($this, 'client')) {
             return $this->client;
         }
         return static::createPantherClient();
    }
}
