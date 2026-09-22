<?php

declare(strict_types=1);

namespace SovereignStack\External\Canvas;

use Psr\Http\Message\ResponseInterface;

/**
 * Public content delivery contract for the Sovereign Canvas (ESPOKE-01).
 *
 * The Canvas NEVER queries the internal content database directly — all
 * content is fetched via BRIDGE-01's DTO transformation layer. This interface
 * is the public-facing render surface that end-users hit through the CDN.
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\External\Canvas
 */
interface ContentDeliveryInterface
{
    /**
     * Render a page by its public slug.
     *
     * Content is fetched via BRIDGE-01 (never directly from ISPOKE-09). If
     * the Bridge is unavailable (503), serves a cached last-known-good
     * page with a stale marker (if one exists), otherwise a proper error page.
     *
     * @param string $slug The public content identifier.
     * @return ResponseInterface The rendered HTTP response.
     */
    public function renderPage(string $slug): ResponseInterface;

    /**
     * Clear the public cache for a specific content item.
     *
     * Called when ISPOKE-09 publishes an update to a public document —
     * the stale cache entry is purged so the next request fetches fresh
     * content through the Bridge.
     *
     * @param string $slug The content identifier to purge.
     */
    public function purgeCache(string $slug): void;
}
