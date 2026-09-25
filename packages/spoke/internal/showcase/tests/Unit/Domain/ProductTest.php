<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Showcase\Tests\Unit\Domain;
use PHPUnit\Framework\TestCase;
use SovereignStack\Spoke\Showcase\Domain\Entity\{Product, ProductStatus};
use SovereignStack\Spoke\Showcase\Domain\ValueObject\{Sku, Price, ProductTitle, ProductSlug};
final class ProductTest extends TestCase
{
    public function testCreateProduct(): void
    {
        $product = Product::create(
            title: ProductTitle::fromString('Wireless Headphones'),
            slug: ProductSlug::fromString('wireless-headphones'),
            sku: Sku::fromString('WH-001'),
            price: Price::fromCents(12999),
        );
        self::assertSame('Wireless Headphones', (string)$product->title());
        self::assertSame('wireless-headphones', (string)$product->slug());
        self::assertSame('WH-001', (string)$product->sku());
        self::assertSame(12999, $product->price()->cents());
        self::assertSame(ProductStatus::Draft, $product->status());
    }
    public function testPublishTransitionsToPublished(): void
    {
        $product = $this->createProduct();
        $product->publish();
        self::assertSame(ProductStatus::Published, $product->status());
    }
    public function testPublishIsIdempotent(): void
    {
        $product = $this->createProduct();
        $product->publish();
        $product->publish(); // second call should be no-op
        self::assertSame(ProductStatus::Published, $product->status());
    }
    private function createProduct(): Product
    {
        return Product::create(
            title: ProductTitle::fromString('Test Product'),
            slug: ProductSlug::fromString('test-product'),
            sku: Sku::fromString('TEST-001'),
            price: Price::fromCents(999),
        );
    }
}
