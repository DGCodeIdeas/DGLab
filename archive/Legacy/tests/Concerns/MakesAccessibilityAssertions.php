<?php

namespace DGLab\Tests\Concerns;

trait MakesAccessibilityAssertions
{
    protected function assertPageIsAccessible(): void
    {
        $client = $this->getClient();
        $axePath = realpath(__DIR__ . '/../Support/axe.min.js');

        if (!file_exists($axePath)) {
            $this->fail("axe.min.js not found at {$axePath}");
        }

        $axeSource = file_get_contents($axePath);
        $client->executeScript($axeSource);

        $results = $client->executeAsyncScript(
            "var callback = arguments[arguments.length - 1];
             axe.run(function (err, results) {
                 callback(results);
             });"
        );

        if (!isset($results['violations'])) {
            $this->fail("Axe failed to return violations.");
        }

        $violations = $results['violations'];
        $criticalViolations = array_filter($violations, function ($v) {
            return in_array($v['impact'], ['critical', 'serious']);
        });

        if (!empty($criticalViolations)) {
            $message = "Accessibility audit failed with " . count($criticalViolations) . " critical/serious violations:\n";
            foreach ($criticalViolations as $v) {
                $message .= "- [{$v['impact']}] {$v['help']} ({$v['helpUrl']})\n";
                foreach ($v['nodes'] as $node) {
                    $message .= "  Target: " . implode(', ', $node['target']) . "\n";
                }
            }
            $this->fail($message);
        }

        $this->assertTrue(true);
    }
}
