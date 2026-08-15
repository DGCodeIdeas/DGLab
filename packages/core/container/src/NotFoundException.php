<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container;

/**
 * Thrown by {@see ContainerInterface::get()} and {@see ContainerInterface::make()}
 * when a service id cannot be resolved: the id is not bound, the concrete
 * class does not exist, the class is not instantiable (abstract, interface,
 * or trait), or a primitive constructor parameter has no default and no
 * resolvable type.
 */
final class NotFoundException extends \RuntimeException implements \Psr\Container\NotFoundExceptionInterface
{
}