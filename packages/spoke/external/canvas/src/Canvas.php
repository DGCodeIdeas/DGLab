<?php

declare(strict_types=1);

namespace SovereignStack\External\Canvas;

use Psr\Http\Message\ResponseInterface;

/**
 * Depth-2 ContentDeliveryInterface implementation.
 *
 * Renders pages from in-memory content (depth-2: no BRIDGE-01/HUB-02). When
 * the full stack lands, this is replaced with a version that fetches content
 * via BRIDGE-01 and caches via HUB-02 — the interface is unchanged.
 *
 * Implements stale-while-revalidate: if content is not found (simulating a
 * Bridge 503), serves the last cached version if available, otherwise a 404.
 *
 * @package SovereignStack\External\Canvas
 */
final class Canvas implements ContentDeliveryInterface
{
    /** @var array<string, string> slug → rendered HTML */
    private array $cache = [];

    /** @var array<string, string> slug → rendered HTML (stale fallback) */
    private array $staleCache = [];

    /** @var array<string, array{title: string, content: string}> slug → raw content */
    private array $content = [];

    /**
     * Publish content for a slug (simulates ISPOKE-09 → BRIDGE-01 → Canvas flow).
     */
    public function publish(string $slug, string $title, string $content): void
    {
        $this->content[$slug] = ['title' => $title, 'content' => $content];
        $rendered = $this->render($slug, $title, $content);
        // Move the old cache entry to stale before updating.
        if (isset($this->cache[$slug])) {
            $this->staleCache[$slug] = $this->cache[$slug];
        }
        $this->cache[$slug] = $rendered;
    }

    public function renderPage(string $slug): ResponseInterface
    {
        // Happy path: content is in the cache.
        if (isset($this->cache[$slug])) {
            return $this->htmlResponse(200, $this->cache[$slug]);
        }

        // Stale-while-revalidate: serve stale cache if available.
        if (isset($this->staleCache[$slug])) {
            // Add a stale marker to the response.
            $html = str_replace('<body>', '<body data-stale="true">', $this->staleCache[$slug]);
            return $this->htmlResponse(200, $html);
        }

        // No cache — try to render from raw content (simulates Bridge fetch).
        if (isset($this->content[$slug])) {
            $data = $this->content[$slug];
            $rendered = $this->render($slug, $data['title'], $data['content']);
            $this->cache[$slug] = $rendered;
            return $this->htmlResponse(200, $rendered);
        }

        // 404 — content not found.
        return $this->htmlResponse(404, '<html><body><h1>Not Found</h1></body></html>');
    }

    public function purgeCache(string $slug): void
    {
        // Move to stale cache before purging (enables stale-while-revalidate fallback).
        if (isset($this->cache[$slug])) {
            $this->staleCache[$slug] = $this->cache[$slug];
        }
        unset($this->cache[$slug]);
    }

    /**
     * Simulate Bridge unavailability — clears the cache but keeps stale.
     * Used for testing the stale-while-revalidate fallback.
     */
    public function simulateBridgeOutage(): void
    {
        foreach ($this->cache as $slug => $html) {
            $this->staleCache[$slug] = $html;
        }
        $this->cache = [];
    }

    private function render(string $slug, string $title, string $content): string
    {
        return sprintf(
            '<!DOCTYPE html><html lang="en"><head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>%s</title>'
            . '<meta name="slug" content="%s">'
            . '</head><body>'
            . '<main>%s</main>'
            . '</body></html>',
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'),
            $content,
        );
    }

    private function htmlResponse(int $status, string $body): ResponseInterface
    {
        $stream = new \SovereignStack\Core\Http\Stream('php://temp', 'r+');
        $stream->write($body);
        $stream->rewind();
        return new \SovereignStack\Core\Http\Response($status, body: $stream);
    }
}
