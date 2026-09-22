<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config;

/**
 * Evaluates feature flags against an immutable Context.
 *
 * Flag definitions are loaded from a FeatureFlagRepositoryInterface (in-memory
 * stub at depth 2; DBAL-backed when CORE-19 lands). For a given Context the
 * result is deterministic — the same user always lands on the same side of
 * a rollout and always sees the same variant.
 *
 * Variants: when the flag's `variants` array is non-null, it maps variant
 * keys to percentage weights (e.g. ["A" => 50, "B" => 50]). getVariant()
 * returns the variant key whose bucket range contains the user's stable hash.
 * If `variants` is null or empty, getVariant() returns "default".
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\Hub\Config
 */
interface FeatureManagerInterface
{
    /**
     * Whether the flag is enabled for the supplied Context.
     *
     * Evaluation order:
     *   1. Flag disabled in definitions → false (short-circuit).
     *   2. enabled, rollout_percentage = 0 → false (no users).
     *   3. enabled, rollout_percentage = 100 → true (all users).
     *   4. enabled, 0 < rollout_percentage < 100 → true iff
     *      RolloutBucket::compute($flag . ':' . $context->userId) < $percentage.
     *
     * If $context is null, a default Context with userId="anonymous" is used;
     * percentage rollouts then resolve consistently for anonymous traffic.
     *
     * @throws UnknownFlagException If the flag key is unknown. Callers that
     *                               prefer "off" semantics should use
     *                               GlobalConfigInterface::feature().
     */
    public function isEnabled(string $flag, ?Context $context = null): bool;

    /**
     * The variant key assigned to this Context for the supplied flag.
     *
     * Returns "default" if the flag has no variants. Returns "off" if the flag
     * is disabled or the Context's rollout bucket places it outside the enabled
     * percentage — callers MUST check isEnabled() first if they need to
     * distinguish "off because disabled" from "off because not in rollout".
     *
     * @throws UnknownFlagException If the flag key is unknown.
     */
    public function getVariant(string $flag, ?Context $context = null): string;
}
