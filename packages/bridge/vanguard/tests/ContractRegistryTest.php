<?php

declare(strict_types=1);

namespace SovereignStack\Bridge\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Bridge\ContractRegistry;
use SovereignStack\Bridge\DefaultDtoTransformer;

/**
 * ContractRegistry tests: default-deny, immutability after first resolve,
 * malformed contract ID rejection.
 *
 * @package SovereignStack\Bridge\Tests
 */
final class ContractRegistryTest extends TestCase
{
    public function testUnregisteredRouteReturnsNull(): void
    {
        $registry = new ContractRegistry();
        self::assertNull($registry->resolve('/unregistered'));
    }

    public function testRegisteredContractResolves(): void
    {
        $registry = new ContractRegistry();
        $transformer = new DefaultDtoTransformer();
        $registry->registerContract('/api/users', $transformer);

        self::assertSame($transformer, $registry->resolve('/api/users'));
    }

    public function testRegistryFreezesAfterFirstResolve(): void
    {
        $registry = new ContractRegistry();
        $registry->registerContract('/route1', new DefaultDtoTransformer());

        // First resolve freezes.
        $registry->resolve('/route1');
        self::assertTrue($registry->isFrozen());

        // Subsequent registerContract throws.
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('immutable after the first resolve()');
        $registry->registerContract('/route2', new DefaultDtoTransformer());
    }

    public function testMalformedContractIdRejected(): void
    {
        $registry = new ContractRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match required pattern');
        $registry->registerContract('UPPER CASE', new DefaultDtoTransformer());
    }

    public function testHasDoesNotFreeze(): void
    {
        $registry = new ContractRegistry();
        $registry->registerContract('/route1', new DefaultDtoTransformer());

        self::assertTrue($registry->has('/route1'));
        self::assertFalse($registry->isFrozen());
    }
}
