<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Showcase\Domain\Exception;
use SovereignStack\Spoke\Showcase\Domain\ValueObject\Sku;
class DuplicateSkuException extends \RuntimeException
{
    public static function forSku(Sku|string $sku): self
    {
        return new self("Duplicate SKU: " . (string) $sku);
    }
}
