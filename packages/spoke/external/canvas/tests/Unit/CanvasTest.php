<?php

declare(strict_types=1);

namespace SovereignStack\External\Canvas\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\External\Canvas\Canvas;
use SovereignStack\External\Canvas\ContentMetadata;
use SovereignStack\External\Canvas\SeoValidator;

/**
 * Canvas tests: render, cache, purge, stale-while-revalidate, SEO validation.
 *
 * @package SovereignStack\External\Canvas\Tests\Unit
 */
final class CanvasTest extends TestCase
{
    public function testRenderPageReturns404ForUnknownSlug(): void
    {
        $canvas = new Canvas();
        $response = $canvas->renderPage('nonexistent');
        self::assertSame(404, $response->getStatusCode());
    }

    public function testPublishThenRenderReturns200(): void
    {
        $canvas = new Canvas();
        $canvas->publish('welcome', 'Welcome Page', '<p>Hello World</p>');

        $response = $canvas->renderPage('welcome');
        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString('Welcome Page', $body);
        self::assertStringContainsString('<p>Hello World</p>', $body);
    }

    public function testPurgeCacheForcesRefetch(): void
    {
        $canvas = new Canvas();
        $canvas->publish('doc', 'Title', '<p>Content</p>');

        // First render populates cache.
        $r1 = $canvas->renderPage('doc');
        self::assertSame(200, $r1->getStatusCode());

        // Purge.
        $canvas->purgeCache('doc');

        // Render again — should still get 200 (content is in the content store).
        $r2 = $canvas->renderPage('doc');
        self::assertSame(200, $r2->getStatusCode());
    }

    public function testStaleWhileRevalidateServesStaleOnBridgeOutage(): void
    {
        $canvas = new Canvas();
        $canvas->publish('doc', 'Title', '<p>Content</p>');

        // First render populates cache.
        $canvas->renderPage('doc');

        // Simulate Bridge outage — clears cache but keeps stale.
        $canvas->simulateBridgeOutage();

        // Render again — should serve stale with data-stale marker.
        $response = $canvas->renderPage('doc');
        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString('data-stale="true"', $body);
    }

    public function testPurgeMovesToStaleBeforeClearing(): void
    {
        $canvas = new Canvas();
        $canvas->publish('doc', 'Title', '<p>Content</p>');
        $canvas->renderPage('doc');

        // Purge — should move to stale.
        $canvas->purgeCache('doc');

        // Simulate outage — no fresh content available.
        $canvas->simulateBridgeOutage();

        // Should still serve stale.
        $response = $canvas->renderPage('doc');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('data-stale', (string) $response->getBody());
    }
}

/**
 * SEO Validator tests.
 *
 * @package SovereignStack\External\Canvas\Tests\Unit
 */
final class SeoValidatorTest extends TestCase
{
    private SeoValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SeoValidator();
    }

    public function testValidMetadataReturnsNoErrors(): void
    {
        $meta = new ContentMetadata(
            title: 'A Valid Page Title',
            description: str_repeat('a', 60),
            canonicalUrl: 'https://example.com/page',
        );
        self::assertSame([], $this->validator->validate($meta));
    }

    public function testTitleTooShort(): void
    {
        $meta = new ContentMetadata(
            title: 'Short',
            description: str_repeat('a', 60),
            canonicalUrl: 'https://example.com',
        );
        $errors = $this->validator->validate($meta);
        self::assertNotEmpty($errors);
        self::assertStringContainsString('Title is too short', $errors[0]);
    }

    public function testTitleTooLong(): void
    {
        $meta = new ContentMetadata(
            title: str_repeat('a', 61),
            description: str_repeat('a', 60),
            canonicalUrl: 'https://example.com',
        );
        $errors = $this->validator->validate($meta);
        self::assertNotEmpty($errors);
        self::assertStringContainsString('Title is too long', $errors[0]);
    }

    public function testDescriptionTooShort(): void
    {
        $meta = new ContentMetadata(
            title: 'Valid Title Here',
            description: 'too short',
            canonicalUrl: 'https://example.com',
        );
        $errors = $this->validator->validate($meta);
        self::assertNotEmpty($errors);
        self::assertStringContainsString('Description is too short', $errors[0]);
    }

    public function testInvalidCanonicalUrl(): void
    {
        $meta = new ContentMetadata(
            title: 'Valid Title Here',
            description: str_repeat('a', 60),
            canonicalUrl: 'not-a-url',
        );
        $errors = $this->validator->validate($meta);
        self::assertNotEmpty($errors);
        self::assertStringContainsString('not a valid URL', $errors[0]);
    }

    public function testTooManyKeywords(): void
    {
        $meta = new ContentMetadata(
            title: 'Valid Title Here',
            description: str_repeat('a', 60),
            canonicalUrl: 'https://example.com',
            keywords: array_fill(0, 11, 'keyword'),
        );
        $errors = $this->validator->validate($meta);
        self::assertNotEmpty($errors);
        self::assertStringContainsString('Too many keywords', $errors[0]);
    }
}
