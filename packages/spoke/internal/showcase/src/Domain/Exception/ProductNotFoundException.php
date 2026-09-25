<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Showcase\Domain\Exception;
use SovereignStack\Spoke\Showcase\Domain\ValueObject\ProductId;
class ProductNotFoundException extends \RuntimeException
{
    public static function forId(ProductId|string $id): self
    {
        return new self("Product not found: " . (string) $id);
    }
}
