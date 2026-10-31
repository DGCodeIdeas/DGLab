<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Showcase\Infrastructure\Persistence;
use DateTimeImmutable;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Internal\Showcase\Domain\Entity\{Product, ProductStatus};
use SovereignStack\Internal\Showcase\Domain\Repository\ProductRepositoryInterface;
use SovereignStack\Internal\Showcase\Domain\ValueObject\{ProductId, Sku, Price, ProductTitle, ProductSlug};
final class MySQLProductRepository implements ProductRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}
    public function get(ProductId $id): ?Product
    {
        $stmt = $this->connection->prepare('SELECT * FROM showcase_products WHERE id = :id');
        $stmt->execute(['id' => $id->value()]);
        /** @var array<string, string|null>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }
    public function getBySlug(ProductSlug $slug): ?Product
    {
        $stmt = $this->connection->prepare('SELECT * FROM showcase_products WHERE slug = :slug');
        $stmt->execute(['slug' => (string) $slug]);
        /** @var array<string, string|null>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }
    public function save(Product $product): void
    {
        $exists = $this->get($product->id()) !== null;
        $data = [
            'id' => $product->id()->value(), 'title' => (string)$product->title(),
            'slug' => (string)$product->slug(), 'sku' => (string)$product->sku(),
            'price_cents' => $product->price()->cents(), 'currency' => $product->price()->currency(),
            'description' => $product->description(), 'status' => $product->status()->value,
        ];
        if ($exists) {
            $stmt = $this->connection->prepare(
                'UPDATE showcase_products SET title=:title, slug=:slug, sku=:sku, price_cents=:price_cents, currency=:currency, description=:description, status=:status, updated_at=NOW() WHERE id=:id',
            );
        } else {
            $stmt = $this->connection->prepare(
                'INSERT INTO showcase_products (id, title, slug, sku, price_cents, currency, description, status, created_at, updated_at) VALUES (:id, :title, :slug, :sku, :price_cents, :currency, :description, :status, NOW(), NOW())',
            );
        }
        $stmt->execute($data);
    }
    public function delete(ProductId $id): void
    {
        $stmt = $this->connection->prepare('DELETE FROM showcase_products WHERE id = :id');
        $stmt->execute(['id' => $id->value()]);
    }
    /**
     * @param array<string, string|null> $row
     */
    private function hydrate(array $row): Product
    {
        // P1-2 fix: restore persisted state without invoking publish() behavior
        return Product::restoreFromPersistence(
            id: new ProductId((string) $row['id']),
            title: ProductTitle::fromString((string) $row['title']),
            slug: ProductSlug::fromString((string) $row['slug']),
            sku: Sku::fromString((string) $row['sku']),
            price: Price::fromCents((int) $row['price_cents'], (string) $row['currency']),
            description: isset($row['description']) ? (string) $row['description'] : null,
            status: ProductStatus::from((string) $row['status']),
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: new DateTimeImmutable((string) ($row['updated_at'] ?? $row['created_at'])),
        );
    }
}
