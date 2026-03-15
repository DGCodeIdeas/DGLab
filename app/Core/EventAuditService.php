<?php

namespace DGLab\Core;

use DGLab\Core\Contracts\EventInterface;
use DGLab\Database\Connection;

/**
 * Class EventAuditService
 *
 * Provides centralized auditing for all event dispatches and listener executions.
 */
class EventAuditService
{
    /**
     * @var Connection The database connection.
     */
    protected Connection $db;

    /**
     * @var string Current dispatch ID for tracing.
     */
    protected string $currentDispatchId;

    /**
     * EventAuditService constructor.
     *
     * @param Connection $db
     */
    public function __construct(Connection $db)
    {
        $this->db = $db;
        $this->currentDispatchId = uniqid('ev_', true);
    }

    /**
     * Log an event dispatch.
     *
     * @param EventInterface $event
     * @return int The ID of the audit log entry.
     */
    public function logDispatch(EventInterface $event): int
    {
        return $this->db->insert(
            "INSERT INTO event_audit_logs (event_class, event_alias, dispatch_id, created_at) VALUES (?, ?, ?, ?)",
            [
                get_class($event),
                $event->getAlias(),
                $this->currentDispatchId,
                date('Y-m-d H:i:s')
            ]
        );
    }

    /**
     * Record a listener execution result.
     *
     * @param int $auditId
     * @param string $listener
     * @param string $driver
     * @param string $status
     * @param int $latencyMs
     * @param string|null $errorMessage
     * @param string|null $stackTrace
     * @return void
     */
    public function logExecution(
        int $auditId,
        string $listener,
        string $driver,
        string $status = 'success',
        int $latencyMs = 0,
        ?string $errorMessage = null,
        ?string $stackTrace = null
    ): void {
        $this->db->insert(
            "INSERT INTO listener_execution_logs (audit_id, listener, driver, status, latency_ms, error_message, stack_trace, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $auditId,
                $listener,
                $driver,
                $status,
                $latencyMs,
                $errorMessage,
                $stackTrace,
                date('Y-m-d H:i:s')
            ]
        );
    }

    /**
     * Get the current dispatch ID for tracing.
     *
     * @return string
     */
    public function getDispatchId(): string
    {
        return $this->currentDispatchId;
    }
}
