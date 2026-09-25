<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Showcase\Domain\Repository;
use SovereignStack\Spoke\Showcase\Domain\Entity\Product;
use SovereignStack\Spoke\Showcase\Domain\ValueObject\{ProductId, ProductSlug};
interface ProductRepositoryInterface
{
    public function get(ProductId $id): ?Product;
    public function getBySlug(ProductSlug $slug): ?Product;
    public function save(Product $product): void;
    public function delete(ProductId $id): void;
}
