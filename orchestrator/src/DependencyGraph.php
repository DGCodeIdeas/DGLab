<?php

declare(strict_types=1);

namespace SovereignStack\Orchestrator;

class DependencyGraph
{
    private const TIER_CORE = 'core';
    private const TIER_HUB = 'hub';
    private const TIER_SPOKE = 'spoke';

    private const VALID_TIERS = [self::TIER_CORE, self::TIER_HUB, self::TIER_SPOKE];

    private const TIER_ORDER = [
        self::TIER_CORE => 0,
        self::TIER_HUB => 1,
        self::TIER_SPOKE => 2,
    ];

    /** @var array<string, array{name: string, tier: string, dependencies: array<int, string>}> */
    private array $nodes = [];

    public function addNode(string $name, string $tier): void
    {
        if (!\in_array($tier, self::VALID_TIERS, true)) {
            throw new \RuntimeException(
                "Invalid tier '{$tier}'. Must be one of: " . \implode(', ', self::VALID_TIERS)
            );
        }

        $this->nodes[$name] = [
            'name' => $name,
            'tier' => $tier,
            'dependencies' => [],
        ];
    }

    public function addDependency(string $node, string $dependsOn): void
    {
        if (!isset($this->nodes[$node])) {
            throw new \RuntimeException("Node '{$node}' is not registered.");
        }

        if (!isset($this->nodes[$dependsOn])) {
            throw new \RuntimeException("Dependency '{$dependsOn}' is not registered.");
        }

        $nodeTier = $this->nodes[$node]['tier'];
        $depTier = $this->nodes[$dependsOn]['tier'];

        // Core repos cannot depend on Hub/Spoke
        if ($nodeTier === self::TIER_CORE && $depTier !== self::TIER_CORE) {
            throw new \RuntimeException(
                "Core node '{$node}' cannot depend on non-Core node '{$dependsOn}'."
            );
        }

        // Prevent self-dependency
        if ($node === $dependsOn) {
            throw new \RuntimeException("Node '{$node}' cannot depend on itself.");
        }

        $this->nodes[$node]['dependencies'][] = $dependsOn;
    }

    /**
     * @return array<int, string>
     */
    public function getResolutionOrder(): array
    {
        // Build adjacency list and in-degree map
        $inDegree = [];
        $adjacency = [];

        foreach ($this->nodes as $name => $data) {
            $inDegree[$name] = 0;
            $adjacency[$name] = [];
        }

        foreach ($this->nodes as $name => $data) {
            foreach ($data['dependencies'] as $dep) {
                $adjacency[$dep][] = $name;
                $inDegree[$name]++;
            }
        }

        // Kahn's algorithm — process Core first, then Hub, then Spoke
        $queue = [];

        foreach (self::TIER_ORDER as $tier => $order) {
            foreach ($this->nodes as $name => $data) {
                if ($data['tier'] === $tier && $inDegree[$name] === 0) {
                    $queue[] = $name;
                }
            }
        }

        $resolved = [];

        while ($queue !== []) {
            $current = \array_shift($queue);
            $resolved[] = $current;

            foreach ($adjacency[$current] as $neighbor) {
                $inDegree[$neighbor]--;

                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }

            // Re-sort queue by tier priority to maintain Core > Hub > Spoke order
            \usort($queue, function (string $a, string $b): int {
                $tierA = self::TIER_ORDER[$this->nodes[$a]['tier']];
                $tierB = self::TIER_ORDER[$this->nodes[$b]['tier']];

                return $tierA <=> $tierB;
            });
        }

        if (\count($resolved) !== \count($this->nodes)) {
            throw new \RuntimeException('Circular dependency detected in the dependency graph.');
        }

        return $resolved;
    }

    /**
     * @param array<int, string> $passedRepos
     */
    public function canEvaluate(string $node, array $passedRepos): bool
    {
        if (!isset($this->nodes[$node])) {
            throw new \RuntimeException("Node '{$node}' is not registered.");
        }

        $dependencies = $this->nodes[$node]['dependencies'];

        if ($dependencies === []) {
            return true;
        }

        foreach ($dependencies as $dep) {
            if (!\in_array($dep, $passedRepos, true)) {
                return false;
            }
        }

        return true;
    }

    public function getTier(string $node): string
    {
        if (!isset($this->nodes[$node])) {
            throw new \RuntimeException("Node '{$node}' is not registered.");
        }

        return $this->nodes[$node]['tier'];
    }
}
