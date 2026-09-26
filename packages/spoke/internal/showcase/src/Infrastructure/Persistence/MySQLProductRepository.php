<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Showcase\Infrastructure\Persistence;
use DateTimeImmutable;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Spoke\Showcase\Domain\Entity\{Product, ProductStatus};
use SovereignStack\Spoke\Showcase\Domain\Exception\ProductNotFoundException;
use SovereignStack\Spoke\Showcase\Domain\Repository\ProductRepositoryInterface;
use SovereignStack\Spoke\Showcase\Domain\ValueObject\{ProductId, Sku, Price, ProductTitle, ProductSlug};
final class MySQLProductRepository implements ProductRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}
    public function get(ProductId $id): ?Product
    {
        $row = $this->connection->fetchOne('SELECT * FROM showcase_products WHERE id = :id', ['id' => (string)$id]);
        return $row ? $this->hydrate($row) : null;
    }
    public function getBySlug(ProductSlug $slug): ?Product
    {
        $row = $this->connection->fetchOne('SELECT * FROM showcase_products WHERE slug = :slug', ['slug' => (string)$slug]);
        return $row ? $this->hydrate($row) : null;
    }
    public function save(Product $product): void
    {
        $exists = $this->get($product->id()) !== null;
        $data = [
            'id' => (string)$product->id(), 'title' => (string)$product->title(),
            'slug' => (string)$product->slug(), 'sku' => (string)$product->sku(),
            'price_cents' => $product->price()->cents(), 'currency' => $product->price()->currency(),
            'description' => $product->description(), 'status' => $product->status()->value,
        ];
        if ($exists) {
            $this->connection->execute(
                'UPDATE showcase_products SET title=:title, slug=:slug, sku=:sku, price_cents=:price_cents, currency=:currency, description=:description, status=:status, updated_at=NOW() WHERE id=:id',
                $data
            );
        } else {
            $this->connection->execute(
                'INSERT INTO showcase_products (id, title, slug, sku, price_cents, currency, description, status, created_at, updated_at) VALUES (:id, :title, :slug, :sku, :price_cents, :currency, :description, :status, NOW(), NOW())',
                $data
            );
        }
    }
    public function delete(ProductId $id): void
    {
        $this->connection->execute('DELETE FROM showcase_products WHERE id = :id', ['id' => (string)$id]);
    }
    private function hydrate(array $row): Product
    {
        $product = new Product(
            id: new ProductId($row['id']),
            title: ProductTitle::fromString($row['title']),
            slug: ProductSlug::fromString($row['slug']),
            sku: Sku::fromString($row['sku']),
            price: Price::fromCents((int)$row['price_cents'], $row['currency']),
            description: $row['description'] ?? null,
            createdAt: new DateTimeImmutable($row['created_at']),
        );
        // P1-2 fix: restore persisted state without invoking publish() behavior
        $status = ProductStatus::from($row['status']);
        return Product::restoreFromPersistence(
            id: new ProductId($row['id']),
            title: ProductTitle::fromString($row['title']),
            slug: ProductSlug::fromString($row['slug']),
            sku: Sku::fromString($row['sku']),
            price: Price::fromCents((int)$row['price_cents'], $row['currency']),
            description: $row['description'] ?? null,
            status: $status,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at'] ?? $row['created_at']),
        );
    }
}
