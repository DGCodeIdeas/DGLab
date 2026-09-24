<?php

declare(strict_types=1);

namespace SovereignStack\Core\Crypto;

/**
 * Multi-key registry contract.
 *
 * The registry holds a map of kid -> raw 32-byte key and an
 * activeKid pointer. Keys can be in one of two states: ACTIVE
 * (eligible for both encryption and decryption) or RETAINED
 * (decrypt-only, after rotation).
 *
 * Frozen per SDLC-AGRD §2.1 on first implementation.
 */
interface KeyRegistryInterface
{
    public function getActiveKey(): string;
    public function getKey(string $kid): string;
    public function addKey(string $kid, string $key): void;
    public function deactivateKey(string $kid): void;
    public function setActiveKey(string $kid): void;
}
