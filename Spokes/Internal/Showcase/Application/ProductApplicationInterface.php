<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Showcase\Application;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Internal\Showcase\Application\Command\CreateProductCommand;
use SovereignStack\Internal\Showcase\Domain\ValueObject\ProductId;

interface ProductApplicationInterface
{
    public function createProduct(CreateProductCommand $cmd): ProductId;

    /**
     * P1-1: publishProduct requires UserId for authorization.
     */
    public function publishProduct(ProductId $id, UserId $publishedBy): void;
}
