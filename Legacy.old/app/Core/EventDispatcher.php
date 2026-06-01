<?php

namespace DGLab\Core;

use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\Contracts\EventDriverInterface;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Core\Contracts\EventSubscriberInterface;
use DGLab\Core\Utils\PatternMatcher;
use DGLab\Core\EventAuditService;

class EventDispatcher implements DispatcherInterface
{
    protected Application $app;
    protected array $listeners = [];
    protected array $sorted = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function dispatch(EventInterface $event): EventInterface
    {
        $auditId = null;
        if ($this->app->has(EventAuditService::class)) {
            $auditId = $this->app->get(EventAuditService::class)->logDispatch($event);
        }

        $listeners = $this->getListenersForEvent($event);
        if (empty($listeners)) {
            return $event;
        }

        $sync = [];
        $async = [];
        foreach ($listeners as $l) {
            if ($l['async']) {
                $async[] = $l['listener'];
            } else {
                $sync[] = $l['listener'];
            }
        }

        if (!empty($sync)) {
            $this->app->get(\DGLab\Core\EventDrivers\SyncDriver::class)->handle($sync, $event, $auditId);
        }
        if (!empty($async)) {
            $this->app->get(\DGLab\Core\EventDrivers\QueueDriver::class)->handle($async, $event, $auditId);
        }

        return $event;
    }

    public function listen(string $pattern, $listener, int $priority = 0, bool $async = false): void
    {
        $this->listeners[$pattern][$priority][] = ['listener' => $listener, 'async' => $async];
        $this->sorted = [];
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $subscriber->subscribe($this);
    }

    public function removeListener(string $pattern, $listener): void
    {
        if (!isset($this->listeners[$pattern])) {
            return;
        }
        foreach ($this->listeners[$pattern] as $prio => &$ls) {
            foreach ($ls as $idx => $reg) {
                if ($reg['listener'] === $listener) {
                    unset($ls[$idx]);
                    $this->sorted = [];
                }
            }
        }
    }

    public function getListenersForEvent(EventInterface $event): array
    {
        $cls = get_class($event);
        $alias = $event->getAlias();
        $key = $cls . ':' . $alias;
        if (isset($this->sorted[$key])) {
            return $this->sorted[$key];
        }

        $matched = [];
        foreach ($this->listeners as $pattern => $prios) {
            if ($pattern === $cls || $pattern === $alias || PatternMatcher::matches($pattern, $alias)) {
                foreach ($prios as $prio => $ls) {
                    foreach ($ls as $l) {
                        $matched[$prio][] = $l;
                    }
                }
            }
        }
        if (empty($matched)) {
            return $this->sorted[$key] = [];
        }
        krsort($matched);
        return $this->sorted[$key] = array_merge(...$matched);
    }
}
