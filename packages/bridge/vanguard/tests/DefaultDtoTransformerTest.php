<?php

declare(strict_types=1);

namespace SovereignStack\Bridge\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Bridge\DefaultDtoTransformer;

/**
 * DTO transformer tests: internal field stripping, redactKeys, nested arrays.
 *
 * @package SovereignStack\Bridge\Tests
 */
final class DefaultDtoTransformerTest extends TestCase
{
    public function testStripsUnderscorePrefixedKeys(): void
    {
        $transformer = new DefaultDtoTransformer();
        $data = ['name' => 'Alice', '_internal_id' => 42, 'email' => 'a@b.com'];

        $result = $transformer->transformResponse($data);
        self::assertSame(['name' => 'Alice', 'email' => 'a@b.com'], $result);
    }

    public function testStripsRedactKeys(): void
    {
        $transformer = new DefaultDtoTransformer(redactKeys: ['password_hash', 'secret']);
        $data = ['name' => 'Alice', 'password_hash' => '$2y$...', 'secret' => 'hidden'];

        $result = $transformer->transformResponse($data);
        self::assertSame(['name' => 'Alice'], $result);
    }

    public function testStripsNestedUnderscoreKeys(): void
    {
        $transformer = new DefaultDtoTransformer();
        $data = [
            'user' => [
                'name' => 'Bob',
                '_internal_role' => 'admin',
            ],
            'settings' => ['theme' => 'dark'],
        ];

        $result = $transformer->transformResponse($data);
        self::assertSame([
            'user' => ['name' => 'Bob'],
            'settings' => ['theme' => 'dark'],
        ], $result);
    }

    public function testNonArrayReturnsUnchanged(): void
    {
        $transformer = new DefaultDtoTransformer();
        self::assertSame('string', $transformer->transformResponse('string'));
        self::assertSame(42, $transformer->transformResponse(42));
        self::assertNull($transformer->transformResponse(null));
    }

    public function testTransformAndTransformResponseBothStrip(): void
    {
        $transformer = new DefaultDtoTransformer();
        $data = ['public' => 1, '_private' => 2];

        self::assertSame(['public' => 1], $transformer->transform($data));
        self::assertSame(['public' => 1], $transformer->transformResponse($data));
    }
}
