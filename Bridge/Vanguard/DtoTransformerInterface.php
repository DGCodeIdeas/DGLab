<?php

declare(strict_types=1);

namespace SovereignStack\Bridge;

/**
 * Transforms data between internal and public-safe representations.
 *
 * One implementation per registered boundary contract. Implementations
 * MUST be deterministic and side-effect-free.
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\Bridge
 */
interface DtoTransformerInterface
{
    /**
     * Transform internal data into a public-safe representation (outbound direction).
     *
     * Strips any field whose key begins with underscore (`_internal_*`).
     *
     * @param mixed $internalData The internal data structure.
     * @return mixed The public-safe DTO.
     */
    public function transform(mixed $internalData): mixed;

    /**
     * Transform an External Spoke's response into a public-safe DTO (inbound direction).
     *
     * Called by the Vanguard on EVERY response. Strips internal fields.
     *
     * @param mixed $publicResponse The External Spoke's response payload.
     * @return mixed The public-safe response. MUST be JSON-serialisable.
     */
    public function transformResponse(mixed $publicResponse): mixed;
}
