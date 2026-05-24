# Phase 19: PSR-14 Event Dispatching

## Objective
Replace the custom `EventDispatcher` with a PSR-14 compliant implementation.

## Technical Requirements
1.  **Dispatcher**: Implement `Psr\EventDispatcher\EventDispatcherInterface`.
2.  **Listener Provider**: Create a provider that implements `Psr\EventDispatcher\ListenerProviderInterface`.
3.  **Stoppable Events**: Support `Psr\EventDispatcher\StoppableEventInterface`.

## Implementation Steps
1.  Update `app/Core/EventDispatcher.php`.
2.  Refactor existing events to follow the new standard.
