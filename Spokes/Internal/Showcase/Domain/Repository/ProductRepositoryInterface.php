<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Showcase\Domain\Repository;
use SovereignStack\Internal\Showcase\Domain\Entity\Product;
use SovereignStack\Internal\Showcase\Domain\ValueObject\{ProductId, ProductSlug};
interface ProductRepositoryInterface
{
    public function get(ProductId $id): ?Product;
    public function getBySlug(ProductSlug $slug): ?Product;
    public function save(Product $product): void;
    public function delete(ProductId $id): void;
}
