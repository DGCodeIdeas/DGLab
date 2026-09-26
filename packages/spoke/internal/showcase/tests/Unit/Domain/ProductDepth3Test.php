<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Showcase\Tests\Unit\Domain;
use PHPUnit\Framework\TestCase;
use SovereignStack\Spoke\Showcase\Domain\Entity\{Product, ProductStatus};
use SovereignStack\Spoke\Showcase\Domain\ValueObject\{Sku, Price, ProductTitle, ProductSlug, ProductId};
use DateTimeImmutable;

/**
 * Depth 3 error-path tests for Showcase Product (Lap 2 deepening).
 *
 * P1-1: authorization consistency
 * P1-2: hydration restores persisted state (no behavior invocation)
 */
final class ProductDepth3Test extends TestCase
{
    /**
     * P1-2: restoreFromPersistence restores status without invoking publish().
     */
    public function testRestoreFromPersistenceDoesNotChangeUpdatedAt(): void
    {
        $createdAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $updatedAt = new DateTimeImmutable('2026-01-15T00:00:00Z');

        $product = Product::restoreFromPersistence(
            id: ProductId::generate(),
            title: ProductTitle::fromString('Test'),
            slug: ProductSlug::fromString('test'),
            sku: Sku::fromString('TEST-001'),
            price: Price::fromCents(999),
            description: null,
            status: ProductStatus::Published,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        // updatedAt must be the persisted value, NOT now()
        self::assertSame($updatedAt, $product->updatedAt());
        self::assertSame(ProductStatus::Published, $product->status());
    }

    /**
     * P1-2: restoreFromPersistence restores archived status.
     */
    public function testRestoreFromPersistenceArchivedStatus(): void
    {
        $product = Product::restoreFromPersistence(
            id: ProductId::generate(),
            title: ProductTitle::fromString('Test'),
            slug: ProductSlug::fromString('test'),
            sku: Sku::fromString('TEST-001'),
            price: Price::fromCents(999),
            description: null,
            status: ProductStatus::Archived,
            createdAt: new DateTimeImmutable('2026-01-01'),
            updatedAt: new DateTimeImmutable('2026-01-15'),
        );

        self::assertSame(ProductStatus::Archived, $product->status());
    }

    /**
     * P1-1: publishProduct requires authorization (interface contract).
     * This test verifies the Product entity's publish() works correctly.
     * The authorization check lives in ProductApplicationService.
     */
    public function testPublishTransitionsFromDraftToPublished(): void
    {
        $product = $this->createTestProduct();
        $product->publish();
        self::assertSame(ProductStatus::Published, $product->status());
    }

    /**
     * P1-1: publish is idempotent (calling twice is safe).
     */
    public function testPublishIsIdempotent(): void
    {
        $product = $this->createTestProduct();
        $product->publish();
        $updatedAt1 = $product->updatedAt();
        $product->publish(); // second call should be no-op
        self::assertSame(ProductStatus::Published, $product->status());
        // Second publish() on already-published should NOT change updatedAt
        self::assertSame($updatedAt1, $product->updatedAt());
    }

    /**
     * P1-2: publish() on a fresh product DOES change updatedAt (behavior, not hydration).
     * This confirms publish() is a state transition, not just a setter.
     */
    public function testPublishChangesUpdatedAtOnFreshProduct(): void
    {
        $product = $this->createTestProduct();
        $beforePublish = $product->updatedAt();
        usleep(1000); // ensure time passes
        $product->publish();
        // publish() should update updatedAt (unlike restoreFromPersistence)
        self::assertNotSame($beforePublish, $product->updatedAt());
    }

    private function createTestProduct(): Product
    {
        return Product::create(
            title: ProductTitle::fromString('Test Product'),
            slug: ProductSlug::fromString('test-product'),
            sku: Sku::fromString('TEST-001'),
            price: Price::fromCents(999),
        );
    }
}
