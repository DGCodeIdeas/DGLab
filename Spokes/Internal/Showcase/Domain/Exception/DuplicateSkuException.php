<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Showcase\Domain\Exception;
use SovereignStack\Internal\Showcase\Domain\ValueObject\Sku;
class DuplicateSkuException extends \RuntimeException
{
    public static function forSku(Sku|string $sku): self
    {
        return new self("Duplicate SKU: " . (string) $sku);
    }
}
