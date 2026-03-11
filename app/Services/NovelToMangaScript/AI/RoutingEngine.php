<?php

/**
 * DGLab LLM Routing Engine
 *
 * Intelligent provider selection based on 10+ factors.
 *
 * @package DGLab\Services\NovelToMangaScript\AI
 */

namespace DGLab\Services\NovelToMangaScript\AI;

/**
 * Class RoutingEngine
 *
 * Multi-factor scoring algorithm for provider selection.
 */
class RoutingEngine
{
    /**
     * Provider repository
     */
    private ProviderRepository $providers;

    /**
     * User context/preferences
     */
    private array $userContext = [];

    /**
     * Recent failure rates per provider
     */
    private array $failureRates = [];

    /**
     * Factor weights (configurable)
     */
    private array $weights = [
        'mode' => 10,           // Mode compatibility (highest priority)
        'cost' => 8,            // Cost efficiency
        'speed' => 7,           // Speed requirements
        'context' => 7,         // Context length support
        'specialization' => 6,  // Task specialization
        'privacy' => 6,         // Privacy requirements
        'reliability' => 5,     // Uptime/reliability
        'compliance' => 5,      // Geographic compliance
        'preference' => 4,      // User preference history
        'capacity' => 3,        // Current load/capacity
    ];

    /**
     * Constructor
     */
    public function __construct(ProviderRepository $providers, array $config = [])
    {
        $this->providers = $providers;
        $this->weights = array_merge($this->weights, $config['weights'] ?? []);
    }

    /**
     * Set user context
     */
    public function setUserContext(array $context): self
    {
        $this->userContext = $context;
        return $this;
    }

    /**
     * Record provider failure
     */
    public function recordFailure(string $providerId): void
    {
        if (!isset($this->failureRates[$providerId])) {
            $this->failureRates[$providerId] = ['failures' => 0, 'attempts' => 0];
        }
        $this->failureRates[$providerId]['failures']++;
        $this->failureRates[$providerId]['attempts']++;
    }

    /**
     * Record provider success
     */
    public function recordSuccess(string $providerId): void
    {
        if (!isset($this->failureRates[$providerId])) {
            $this->failureRates[$providerId] = ['failures' => 0, 'attempts' => 0];
        }
        $this->failureRates[$providerId]['attempts']++;
    }

    /**
     * Select best provider for request
     *
     * @param array $request Request parameters
     * @param array $constraints Hard constraints
     * @return RankedProviders
     */
    public function selectProvider(array $request, array $constraints = []): RankedProviders
    {
        $candidates = $this->providers->getAll();
        $scores = [];

        foreach ($candidates as $providerId => $providerDef) {
            try {
                $provider = $this->providers->get($providerId);
            } catch (\Throwable $e) {
                continue; // Skip unavailable providers
            }

            $score = 0;
            $factors = [];
            $eliminated = false;

            // Factor 1: Mode compatibility (weight: 10)
            $modeScore = $this->scoreModeCompatibility($providerDef, $request['mode'] ?? 'censored');
            if ($modeScore === 0.0) {
                continue; // Eliminate incompatible
            }
            $score += $modeScore * $this->weights['mode'];
            $factors['mode'] = $modeScore;

            // Factor 2: Cost efficiency (weight: 8)
            $costScore = $this->scoreCostEfficiency($provider, $request['estimated_tokens'] ?? 1000);
            $score += $costScore * $this->weights['cost'];
            $factors['cost'] = $costScore;

            // Factor 3: Speed requirements (weight: 7)
            $speedScore = $this->scoreSpeedMatch($provider, $request['speed_requirement'] ?? 'standard');
            $score += $speedScore * $this->weights['speed'];
            $factors['speed'] = $speedScore;

            // Factor 4: Context length (weight: 7)
            $contextScore = $this->scoreContextFit($provider, $request['context_length'] ?? 4096);
            if ($contextScore === 0.0) {
                continue; // Can't handle context
            }
            $score += $contextScore * $this->weights['context'];
            $factors['context'] = $contextScore;

            // Factor 5: Specialization match (weight: 6)
            $specScore = $this->scoreSpecialization($provider, $request['task_type'] ?? 'general');
            $score += $specScore * $this->weights['specialization'];
            $factors['specialization'] = $specScore;

            // Factor 6: Privacy requirements (weight: 6)
            $privacyScore = $this->scorePrivacyMatch($providerDef, $request['privacy_level'] ?? 'standard');
            $score += $privacyScore * $this->weights['privacy'];
            $factors['privacy'] = $privacyScore;

            // Factor 7: Reliability/uptime (weight: 5)
            $reliabilityScore = $this->scoreReliability($providerId);
            $score += $reliabilityScore * $this->weights['reliability'];
            $factors['reliability'] = $reliabilityScore;

            // Factor 8: Geographic compliance (weight: 5)
            $complianceScore = $this->scoreCompliance($providerDef, $constraints['required_compliance'] ?? []);
            if ($complianceScore === 0.0 && !empty($constraints['required_compliance'])) {
                continue; // Compliance requirement not met
            }
            $score += $complianceScore * $this->weights['compliance'];
            $factors['compliance'] = $complianceScore;

            // Factor 9: User preference history (weight: 4)
            $prefScore = $this->scoreUserPreference($providerId);
            $score += $prefScore * $this->weights['preference'];
            $factors['preference'] = $prefScore;

            // Factor 10: Current capacity (weight: 3)
            $capacityScore = 0.9; // Default to high availability
            $score += $capacityScore * $this->weights['capacity'];
            $factors['capacity'] = $capacityScore;

            // Apply failure penalty
            $failureRate = $this->getRecentFailureRate($providerId);
            $score *= (1 - $failureRate);

            $scores[$providerId] = [
                'total' => $score,
                'factors' => $factors,
                'failure_rate' => $failureRate,
            ];
        }

        // Sort by total score
        uasort($scores, fn($a, $b) => $b['total'] <=> $a['total']);

        $ranked = array_keys($scores);
        $primary = $ranked[0] ?? null;
        $alternatives = array_slice($ranked, 1, 3);

        return new RankedProviders(
            primary: $primary,
            alternatives: $alternatives,
            scores: $scores,
            explanation: $this->generateExplanation($scores, $request, $primary)
        );
    }

    /**
     * Select model for provider
     */
    public function selectModel(string $providerId, array $request): string
    {
        $provider = $this->providers->get($providerId);
        $models = $provider->getModels();

        $bestModel = null;
        $bestScore = -1;

        foreach ($models as $modelId => $config) {
            $score = 0;

            // Context length support
            $requiredContext = $request['context_length'] ?? 4096;
            if (($config['context_tokens'] ?? 0) < $requiredContext) {
                continue;
            }
            $score += 10;

            // Speed tier match
            $requiredSpeed = $request['speed_requirement'] ?? 'standard';
            $modelSpeed = $config['speed_tier'] ?? 'standard';
            if ($this->speedTierMatches($modelSpeed, $requiredSpeed)) {
                $score += 8;
            }

            // Specialization match
            $taskType = $request['task_type'] ?? 'general';
            $specs = $config['specializations'] ?? ['general'];
            if (in_array($taskType, $specs) || in_array('general', $specs)) {
                $score += 6;
            }

            // Cost efficiency (prefer cheaper if quality isn't critical)
            $costInput = $config['cost_input'] ?? 0;
            if ($costInput < 0.001) {
                $score += 4;
            } elseif ($costInput < 0.005) {
                $score += 2;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestModel = $modelId;
            }
        }

        return $bestModel ?? array_key_first($models);
    }

    /**
     * Score mode compatibility
     */
    private function scoreModeCompatibility(array $provider, string $mode): float
    {
        $providerMode = $provider['censorship_default'] ?? 'censored';

        return match ($mode) {
            'censored' => in_array($providerMode, ['censored', 'configurable', 'varies']) ? 1.0 : 0.5,
            'uncensored' => in_array($providerMode, ['uncensored', 'minimal', 'configurable']) ? 1.0 : 0.0,
            'auto' => 0.8,
            default => 0.5,
        };
    }

    /**
     * Score cost efficiency
     */
    private function scoreCostEfficiency($provider, int $estimatedTokens): float
    {
        $pricing = $provider->getPricing();
        $avgCost = 0;
        $count = 0;

        foreach ($pricing as $model => $costs) {
            $avgCost += ($costs['input'] + $costs['output']) / 2;
            $count++;
        }

        if ($count === 0) {
            return 0.5;
        }

        $avgCost /= $count;
        $estimatedCost = ($estimatedTokens / 1000) * $avgCost;

        // Get budget preference
        $budget = $this->userContext['budget'] ?? 'moderate';

        return match ($budget) {
            'tight' => $estimatedCost < 0.01 ? 1.0 : ($estimatedCost < 0.05 ? 0.5 : 0.1),
            'moderate' => $estimatedCost < 0.10 ? 1.0 : ($estimatedCost < 0.50 ? 0.7 : 0.3),
            'unlimited' => 1.0,
            default => 0.5,
        };
    }

    /**
     * Score speed match
     */
    private function scoreSpeedMatch($provider, string $requiredSpeed): float
    {
        $models = $provider->getModels();
        $hasMatchingSpeed = false;

        foreach ($models as $config) {
            $modelSpeed = $config['speed_tier'] ?? 'standard';
            if ($this->speedTierMatches($modelSpeed, $requiredSpeed)) {
                $hasMatchingSpeed = true;
                break;
            }
        }

        return $hasMatchingSpeed ? 1.0 : 0.5;
    }

    /**
     * Check if speed tier matches requirement
     */
    private function speedTierMatches(string $available, string $required): bool
    {
        $tiers = ['batch' => 0, 'standard' => 1, 'fast' => 2, 'ultra' => 3];

        $availableLevel = $tiers[$available] ?? 1;
        $requiredLevel = $tiers[$required] ?? 1;

        return $availableLevel >= $requiredLevel;
    }

    /**
     * Score context fit
     */
    private function scoreContextFit($provider, int $requiredContext): float
    {
        $models = $provider->getModels();
        $maxContext = 0;

        foreach ($models as $config) {
            $maxContext = max($maxContext, $config['context_tokens'] ?? 0);
        }

        if ($maxContext < $requiredContext) {
            return 0.0; // Cannot handle required context
        }

        // Prefer providers with more headroom
        $ratio = $maxContext / $requiredContext;
        return min(1.0, $ratio / 4);
    }

    /**
     * Score specialization match
     */
    private function scoreSpecialization($provider, string $task): float
    {
        $models = $provider->getModels();
        $bestMatch = 0.4;

        $taskMapping = [
            'manga_script' => ['creative', 'general'],
            'scene_analysis' => ['analytical', 'general'],
            'dialogue' => ['creative', 'roleplay'],
            'technical' => ['technical', 'analytical'],
            'multilingual' => ['multilingual', 'general'],
            'general' => ['general'],
        ];

        $preferredSpecs = $taskMapping[$task] ?? ['general'];

        foreach ($models as $config) {
            $specs = $config['specializations'] ?? ['general'];

            foreach ($preferredSpecs as $pref) {
                if (in_array($pref, $specs)) {
                    $bestMatch = max($bestMatch, $pref === $preferredSpecs[0] ? 1.0 : 0.7);
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Score privacy match
     */
    private function scorePrivacyMatch(array $provider, string $privacyLevel): float
    {
        $deploymentType = $provider['deployment_type'] ?? 'saas';
        $hasTrainingOptOut = $provider['training_opt_out'] ?? false;

        return match ($privacyLevel) {
            'maximum' => $deploymentType === 'local' ? 1.0 : 0.0,
            'high' => $deploymentType === 'local' ? 1.0 : ($hasTrainingOptOut ? 0.7 : 0.3),
            'standard' => 0.8,
            default => 0.8,
        };
    }

    /**
     * Score reliability
     */
    private function scoreReliability(string $providerId): float
    {
        // Use failure rate if available
        $failureRate = $this->getRecentFailureRate($providerId);
        return 1.0 - $failureRate;
    }

    /**
     * Score compliance
     */
    private function scoreCompliance(array $provider, array $required): float
    {
        if (empty($required)) {
            return 1.0;
        }

        $providerCompliance = $provider['compliance'] ?? [];
        $matched = array_intersect($required, $providerCompliance);

        return count($matched) / count($required);
    }

    /**
     * Score user preference
     */
    private function scoreUserPreference(string $providerId): float
    {
        $preferredProviders = $this->userContext['preferred_providers'] ?? [];
        $blockedProviders = $this->userContext['blocked_providers'] ?? [];

        if (in_array($providerId, $blockedProviders)) {
            return 0.0;
        }

        if (in_array($providerId, $preferredProviders)) {
            return 1.0;
        }

        return 0.5;
    }

    /**
     * Get recent failure rate for provider
     */
    private function getRecentFailureRate(string $providerId): float
    {
        if (!isset($this->failureRates[$providerId])) {
            return 0.0;
        }

        $data = $this->failureRates[$providerId];
        if ($data['attempts'] === 0) {
            return 0.0;
        }

        return $data['failures'] / $data['attempts'];
    }

    /**
     * Generate explanation for selection
     */
    private function generateExplanation(array $scores, array $request, ?string $primary): string
    {
        if (!$primary || !isset($scores[$primary])) {
            return 'No suitable provider found for the request.';
        }

        $topData = $scores[$primary];
        $factors = $topData['factors'];

        $parts = ["Selected {$primary} because:"];

        // Highlight top 3 factors
        arsort($factors);
        $topFactors = array_slice($factors, 0, 3, true);

        foreach ($topFactors as $factor => $score) {
            if ($score > 0.7) {
                $parts[] = "- Excellent {$factor} match (" . round($score * 100) . "%)";
            } elseif ($score > 0.5) {
                $parts[] = "- Good {$factor} compatibility (" . round($score * 100) . "%)";
            }
        }

        if (($request['mode'] ?? 'censored') === 'uncensored') {
            $parts[] = "- Supports uncensored mode";
        }

        return implode("\n", $parts);
    }
}

/**
 * Class RankedProviders
 *
 * Result of provider ranking.
 */
class RankedProviders
{
    public function __construct(
        public readonly ?string $primary,
        public readonly array $alternatives,
        public readonly array $scores,
        public readonly string $explanation
    ) {}

    /**
     * Get all ranked providers in order
     */
    public function getAllRanked(): array
    {
        if ($this->primary === null) {
            return $this->alternatives;
        }
        return array_merge([$this->primary], $this->alternatives);
    }

    /**
     * Get fallback provider
     */
    public function getFallback(): ?string
    {
        return $this->alternatives[0] ?? null;
    }

    /**
     * Check if has alternatives
     */
    public function hasAlternatives(): bool
    {
        return !empty($this->alternatives);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'primary' => $this->primary,
            'alternatives' => $this->alternatives,
            'scores' => $this->scores,
            'explanation' => $this->explanation,
        ];
    }
}
