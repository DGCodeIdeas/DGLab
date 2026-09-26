<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Showcase\Application\Service;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\{RoleIdentifier, UserId};
use SovereignStack\Spoke\Showcase\Application\Command\CreateProductCommand;
use SovereignStack\Spoke\Showcase\Application\ProductApplicationInterface;
use SovereignStack\Spoke\Showcase\Domain\Entity\Product;
use SovereignStack\Spoke\Showcase\Domain\Repository\ProductRepositoryInterface;
use SovereignStack\Spoke\Showcase\Domain\ValueObject\ProductId;

final class ProductApplicationService implements ProductApplicationInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly IdentityInterface $identity,
    ) {}

    public function createProduct(CreateProductCommand $cmd): ProductId
    {
        $this->requireAnyRole($cmd->createdBy, ['showcase:admin', 'showcase:editor', 'platform:admin']);
        $product = Product::create(
            title: $cmd->title, slug: $cmd->slug, sku: $cmd->sku,
            price: $cmd->price, description: $cmd->description
        );
        $this->products->save($product);
        return $product->id();
    }

    /**
     * P1-1 fix: publishProduct now requires authorization.
     * Per Lap 2 depth-3: every protected application operation must have
     * an explicit authorization check, not just creation.
     */
    public function publishProduct(ProductId $id, UserId $publishedBy): void
    {
        $this->requireAnyRole($publishedBy, ['showcase:admin', 'showcase:editor', 'platform:admin']);
        $product = $this->products->get($id)
            ?? throw new \SovereignStack\Spoke\Showcase\Domain\Exception\ProductNotFoundException($id);
        $product->publish();
        $this->products->save($product);
    }

    private function requireAnyRole(UserId $userId, array $roles): void
    {
        $roleIds = array_map(fn($r) => RoleIdentifier::fromString($r), $roles);
        if (!$this->identity->hasAnyRole($userId, ...$roleIds)) {
            throw new \RuntimeException('Not authorized', 403);
        }
    }
}
