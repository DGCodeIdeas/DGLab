<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Showcase\Domain\Entity;
use DateTimeImmutable;
use SovereignStack\Spoke\Showcase\Domain\ValueObject\{ProductId, Sku, Price, ProductTitle, ProductSlug};
enum ProductStatus: string { case Draft = 'draft'; case Published = 'published'; case Archived = 'archived'; }
final class Product
{
    private ProductStatus $status = ProductStatus::Draft;
    private DateTimeImmutable $updatedAt;
    public function __construct(
        private readonly ProductId $id,
        private ProductTitle $title,
        private ProductSlug $slug,
        private Sku $sku,
        private Price $price,
        private ?string $description = null,
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->updatedAt = new DateTimeImmutable();
    }
    public static function create(
        ProductTitle $title, ProductSlug $slug, Sku $sku, Price $price, ?string $description = null
    ): self {
        return new self(
            id: ProductId::generate(),
            title: $title, slug: $slug, sku: $sku, price: $price, description: $description
        );
    }
    public function id(): ProductId { return $this->id; }
    public function title(): ProductTitle { return $this->title; }
    public function slug(): ProductSlug { return $this->slug; }
    public function sku(): Sku { return $this->sku; }
    public function price(): Price { return $this->price; }
    public function description(): ?string { return $this->description; }
    public function status(): ProductStatus { return $this->status; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }
    public function publish(): void
    {
        if ($this->status === ProductStatus::Archived) {
            throw new \SovereignStack\Spoke\Showcase\Domain\Exception\ProductNotFoundException($this->id);
        }
        if ($this->status === ProductStatus::Published) return; // idempotent
        $this->status = ProductStatus::Published;
        $this->updatedAt = new DateTimeImmutable();
    }
}
