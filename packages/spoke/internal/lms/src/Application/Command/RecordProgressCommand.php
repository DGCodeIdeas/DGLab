<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Application\Command;

use SovereignStack\Spoke\Lms\Domain\ValueObject\{EnrollmentId, ModuleId, ModuleStatus};

/**
 * Command to record progress on a module within an enrollment.
 *
 * Per SAAI design review 2026-10-20 (Issue B — trusted actor identity):
 * This command does NOT contain a `recordedBy` field. The authenticated
 * actor's identity is established by the HTTP layer (session cookie or
 * JWT — once auth transport is decided in ADR-022) and passed separately
 * to the application service as a `UserId $actor` parameter. This prevents
 * a caller from claiming to be another user by supplying their ID in the
 * request body.
 *
 * For audit purposes, the service records the actor in an audit log (when
 * one exists) — but never accepts it from the command.
 */
final readonly class RecordProgressCommand
{
    public function __construct(
        public EnrollmentId $enrollmentId,
        public ModuleId $moduleId,
        public ModuleStatus $newStatus,
    ) {
    }
}
