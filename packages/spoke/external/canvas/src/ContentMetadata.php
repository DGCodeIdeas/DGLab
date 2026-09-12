<?php

declare(strict_types=1);

namespace SovereignStack\External\Canvas;

/**
 * Immutable content metadata value object for SEO validation.
 *
 * Carries the fields a content author sets at publish time. The
 * SeoValidationInterface validates these before the content reaches
 * the render path.
 *
 * @package SovereignStack\External\Canvas
 */
final class ContentMetadata
{
    /**
     * @param string      $title           Page title (required, 10-60 chars).
     * @param string      $description     Meta description (required, 50-160 chars).
     * @param string      $canonicalUrl    Canonical URL (required, must be a valid URL).
     * @param string|null $ogImage         Open Graph image URL (optional).
     * @param array<string> $keywords      SEO keywords (optional, max 10).
     */
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $canonicalUrl,
        public readonly ?string $ogImage = null,
        public readonly array $keywords = [],
    ) {
    }
}
