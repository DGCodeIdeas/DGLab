<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Showcase\Application;
use SovereignStack\Spoke\Showcase\Domain\ValueObject\ProductId;
interface ProductApplicationInterface
{
    public function createProduct(CreateProductCommand $cmd): ProductId;
    public function publishProduct(ProductId $id): void;
}
