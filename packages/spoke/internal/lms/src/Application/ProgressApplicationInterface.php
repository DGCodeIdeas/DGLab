<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Application;

use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Spoke\Lms\Application\Command\RecordProgressCommand;
use SovereignStack\Spoke\Lms\Domain\Entity\Progress;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{EnrollmentId, ModuleId};

/**
 * Application contract for progress lifecycle operations.
 *
 * NEW interface created in Increment 3 PR-A2.
 *
 * Authorization contract (per SAAI design review 2026-10-20):
 *   - recordProgress() requires self OR lms:admin/instructor/platform:admin
 *   - getProgress() requires self OR admin-class role; returns null for
 *     both not-found AND unauthorized (avoid leaking existence)
 *   - listProgressForEnrollment() requires self OR admin-class role
 *
 * Per SAAI Issue B (trusted actor identity):
 *   The actor's UserId is passed as a separate parameter to each method,
 *   NOT embedded in the command. The HTTP layer derives the actor from
 *   trusted identity context (session cookie or JWT — once ADR-022 is
 *   authorized). The application service performs authorization against
 *   the actor.
 *
 * Per SAAI Issue C (validation):
 *   recordProgress() performs the full validation chain:
 *   1. Load enrollment (throw EnrollmentNotFoundException if missing)
 *   2. Check enrollment is Active (not Cancelled/Completed)
 *   3. Load course (verify Published, not Archived)
 *   4. Load module (verify it belongs to the enrollment's course)
 *   5. Load existing progress (for state-machine validation)
 *   6. Validate state transition (forward-only; completed → in_progress
 *      throws CannotRevertCompletedModuleException)
 *   7. Authorize (self or admin)
 *   8. Apply transition + persist (transaction-wrapped)
 *
 * Per SAAI Issue A (single source of truth):
 *   The lms_progress table is the canonical record. Enrollment::recordProgress()
 *   is a domain validation operation; ProgressRepository::save() is the
 *   persistence authority. The application service coordinates both.
 */
interface ProgressApplicationInterface
{
    /**
     * Record progress on a module within an enrollment.
     *
     * @param RecordProgressCommand $cmd Contains enrollmentId, moduleId, newStatus.
     *                                   Does NOT contain recordedBy (per SAAI Issue B).
     * @param UserId $actor The authenticated caller's identity (derived from
     *                       trusted context by the HTTP layer, NOT from the
     *                       request body).
     * @throws \SovereignStack\Spoke\Lms\Domain\Exception\EnrollmentNotFoundException If enrollment not found.
     * @throws \RuntimeException If enrollment not Active, course Archived, module
     *                            doesn't belong to course, or caller unauthorized.
     * @throws \SovereignStack\Spoke\Lms\Domain\Exception\CannotRevertCompletedModuleException If attempting
     *                            to revert a completed module back to in_progress.
     */
    public function recordProgress(RecordProgressCommand $cmd, UserId $actor): void;

    /**
     * Get progress for a specific (enrollment, module) pair.
     *
     * @return Progress|null Null if not found OR if caller is unauthorized
     *                       (security: avoid leaking existence).
     */
    public function getProgress(EnrollmentId $enrollmentId, ModuleId $moduleId, UserId $actor): ?Progress;

    /**
     * List all progress records for an enrollment.
     *
     * @return Progress[] Ordered by module_id ASC.
     */
    public function listProgressForEnrollment(EnrollmentId $enrollmentId, UserId $actor): array;
}
