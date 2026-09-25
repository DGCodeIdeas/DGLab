<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Showcase\Application\Command;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Spoke\Showcase\Domain\ValueObject\{Sku, Price, ProductTitle, ProductSlug};
final readonly class CreateProductCommand
{
    public function __construct(
        public UserId $createdBy,
        public ProductTitle $title,
        public ProductSlug $slug,
        public Sku $sku,
        public Price $price,
        public ?string $description = null,
    ) {}
}
