# EventDispatcher - Phase 2: Advanced Routing & Control

**Status**: COMPLETED
**Source**: `Blueprint/EventDispatcher/PHASE_2_ROUTING_CONTROL.md`

## Objectives
- [ ] Support for `*` and `**` wildcards in event names (e.g., `user.*`, `system.**`).
- [ ] Implementation of a `PatternMatcher` utility within the `EventDispatcher` to efficiently find all listeners matching a dispatched event's name.
- [ ] Based Execution
- [ ] Extension of the `listen()` method to accept an optional `priority` integer (default: 0).
- [ ] Automatic sorting of listeners by priority (highest to lowest) before execution.
- [ ] Introduction of a `StoppableEventInterface` or a base `Event` class with a `isPropagationStopped()` method.
- [ ] The `EventDispatcher` must check this state after every listener execution and halt further processing if requested.

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.
