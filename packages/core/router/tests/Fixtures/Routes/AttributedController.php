<?php
declare(strict_types=1);

namespace SovereignStack\Core\Router\Tests\Fixtures\Routes;

use SovereignStack\Core\Router\RouteAttribute;

final class AttributedController
{
    #[RouteAttribute('/users', ['GET'], name: 'users.index', middleware: ['AuthMiddleware'])]
    public function index(): void {}

    #[RouteAttribute('/users/{id}', ['GET', 'HEAD'], name: 'users.show', constraints: ['id' => '\d+'])]
    public function show(): void {}

    #[RouteAttribute('/users/{slug}', ['GET'], name: 'users.by-slug', constraints: ['slug' => '[a-z0-9-]+'])]
    public function bySlug(): void {}

    #[RouteAttribute('/posts/{slug}', ['GET'], name: 'posts.show', constraints: ['slug' => '[a-z0-9-]+'])]
    public function postShow(): void {}

    #[RouteAttribute('/api/health', ['GET'])]
    public function health(): void {}
}
