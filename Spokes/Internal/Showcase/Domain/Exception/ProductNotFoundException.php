<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Showcase\Domain\Exception;
use SovereignStack\Internal\Showcase\Domain\ValueObject\ProductId;
class ProductNotFoundException extends \RuntimeException
{
    public static function forId(ProductId|string $id): self
    {
        return new self("Product not found: " . (string) $id);
    }
}
