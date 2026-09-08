<?php
declare(strict_types=1);

namespace SovereignStack\Core\Router\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Router\Route;
use SovereignStack\Core\Router\RouteCompiler;
use SovereignStack\Core\Router\Exception\InvalidRoutePatternException;

final class RouteCompilerTest extends TestCase
{
    private RouteCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new RouteCompiler();
    }

    /** @param array<string,string> $constraints */
    private function createRoute(string $path, array $constraints = []): Route
    {
        return new Route(
            path: $path,
            methods: ['GET'],
            name: 'test',
            controllerClass: 'TestController',
            controllerMethod: 'test',
            constraints: $constraints,
        );
    }

    public function testNoPlaceholderPath(): void
    {
        $compiled = $this->compiler->compile($this->createRoute('/users'));
        self::assertSame('#^/users$#u', $compiled->regex);
        self::assertSame([], $compiled->placeholderNames);
    }

    public function testSinglePlaceholder(): void
    {
        $compiled = $this->compiler->compile($this->createRoute('/users/{id}'));
        self::assertSame('#^/users/(?P<id>[^/]+)$#u', $compiled->regex);
        self::assertSame(['id'], $compiled->placeholderNames);
    }

    public function testMultiplePlaceholders(): void
    {
        $compiled = $this->compiler->compile($this->createRoute('/posts/{category}/{slug}'));
        self::assertSame(['category', 'slug'], $compiled->placeholderNames);
    }

    public function testInlineConstraint(): void
    {
        $compiled = $this->compiler->compile($this->createRoute('/users/{id:\d+}'));
        self::assertSame('#^/users/(?P<id>\d+)$#u', $compiled->regex);
    }

    public function testConstraintsMapOverride(): void
    {
        $compiled = $this->compiler->compile(
            $this->createRoute('/users/{id}', ['id' => '\d+'])
        );
        self::assertSame('#^/users/(?P<id>\d+)$#u', $compiled->regex);
    }

    public function testDuplicatePlaceholderThrows(): void
    {
        $this->expectException(InvalidRoutePatternException::class);
        $this->compiler->compile($this->createRoute('/users/{id}/posts/{id}'));
    }

    public function testPathTraversalThrows(): void
    {
        $this->expectException(InvalidRoutePatternException::class);
        $this->compiler->compile($this->createRoute('/users/../etc'));
    }

    public function testDotSegmentThrows(): void
    {
        $this->expectException(InvalidRoutePatternException::class);
        $this->compiler->compile($this->createRoute('/users/./list'));
    }

    public function testDefaultConstraintIsNonSlash(): void
    {
        $compiled = $this->compiler->compile($this->createRoute('/search/{q}'));
        self::assertStringContainsString('[^/]+', $compiled->regex);
    }

    public function testUlidConstraint(): void
    {
        $compiled = $this->compiler->compile(
            $this->createRoute('/items/{ulid}', ['ulid' => '[0-9A-HJKMNP-TV-Z]{26}'])
        );
        self::assertStringContainsString('[0-9A-HJKMNP-TV-Z]{26}', $compiled->regex);
    }
}
