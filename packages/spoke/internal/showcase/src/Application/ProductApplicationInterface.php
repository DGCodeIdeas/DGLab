<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Showcase\Application;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Spoke\Showcase\Domain\ValueObject\ProductId;

interface ProductApplicationInterface
{
    public function createProduct(CreateProductCommand $cmd): ProductId;

    /**
     * P1-1: publishProduct requires UserId for authorization.
     */
    public function publishProduct(ProductId $id, UserId $publishedBy): void;
}
