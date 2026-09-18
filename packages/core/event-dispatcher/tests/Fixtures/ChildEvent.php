<?php

declare(strict_types=1);

namespace SovereignStack\Core\EventDispatcher\Tests\Fixtures;

use SovereignStack\Core\EventDispatcher\Event;

/**
 * Child event fixture for testing type-hierarchy listener dispatch.
 *
 * Listeners registered for the parent class ({@see TestEvent}) MUST also
 * fire for child events ({@see ChildEvent}), because ListenerProvider's
 * collectAndSortListeners() walks the full class hierarchy via
 * getTypeHierarchy() — parent classes and implemented interfaces.
 *
 * Extends TestEvent (not Event directly) so that parent-registered
 * listeners see the same `processed` / `data['handled_by']` shape.
 */
final class ChildEvent extends TestEvent
{
    public function __construct(
        public readonly string $childMarker = 'child',
    ) {
        parent::__construct('child-event');
    }
}
