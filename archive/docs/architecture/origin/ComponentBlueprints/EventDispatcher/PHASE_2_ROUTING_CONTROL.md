# Phase 2: Advanced Routing & Control

## Overview
Phase 2 enhances the Event Dispatcher with sophisticated routing logic. It introduces the ability to prioritize listeners, stop the propagation of events, and use wildcard patterns for broad event subscription.

## Key Components

### 1. Wildcard Pattern Matching
- Support for `*` and `**` wildcards in event names (e.g., `user.*`, `system.**`).
- Implementation of a `PatternMatcher` utility within the `EventDispatcher` to efficiently find all listeners matching a dispatched event's name.

### 2. Priority-Based Execution
- Extension of the `listen()` method to accept an optional `priority` integer (default: 0).
- Automatic sorting of listeners by priority (highest to lowest) before execution.

### 3. Propagation Control
- Introduction of a `StoppableEventInterface` or a base `Event` class with a `isPropagationStopped()` method.
- The `EventDispatcher` must check this state after every listener execution and halt further processing if requested.

### 4. Advanced Registration
- **Event Subscribers**: Support for classes that define multiple `onEventName` methods, allowing related event logic to be grouped in a single class.
- **Closure Support**: Ensuring anonymous functions can be registered with full priority and wildcard support.

## Success Criteria
- [ ] Wildcard listeners (e.g., `audit.*`) successfully trigger for all matching events.
- [ ] Listeners with higher priority execute before those with lower priority.
- [ ] A listener can successfully stop event propagation, preventing subsequent listeners from running.
- [ ] `EventSubscriber` classes can be registered and their methods called correctly.
