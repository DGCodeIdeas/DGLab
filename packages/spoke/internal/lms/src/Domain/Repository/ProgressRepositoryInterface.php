<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Domain\Repository;

use SovereignStack\Spoke\Lms\Domain\Entity\Progress;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{EnrollmentId, ModuleId};

/**
 * Repository contract for Progress persistence.
 *
 * NEW interface created in Increment 3 PR-A2. The `Progress` entity existed
 * since Increment 2 (PR #356) but had no repository — its state was only
 * tracked in `Enrollment`'s in-memory `$progress` array, which was never
 * persisted. This interface defines the canonical persistence contract.
 *
 * Per SAAI design review 2026-10-20 (Issue A — single source of truth):
 * The `lms_progress` table (migration 004) is the canonical record for
 * per-(enrollment, module) progress state. The composite PK
 * `(enrollment_id, module_id)` ensures one record per module per
 * enrollment. The `Enrollment::recordProgress()` method is a domain
 * validation operation; this repository is the persistence authority.
 *
 * Per SAAI design review 2026-10-20 (Issue D — persistence semantics):
 * `save()` takes both `Progress` and `EnrollmentId` because the `Progress`
 * entity does not carry its `enrollment_id` (the entity's constructor
 * takes only `moduleId, status, startedAt, completedAt`). The enrollment
 * context is implicit (the Progress belongs to the enrollment whose ID
 * is the first half of the composite PK).
 */
interface ProgressRepositoryInterface
{
    /**
     * Get progress for a specific (enrollment, module) pair.
     *
     * @return Progress|null Null if no progress record exists for this pair.
     */
    public function get(EnrollmentId $enrollmentId, ModuleId $moduleId): ?Progress;

    /**
     * Find all progress records for an enrollment, ordered by module_id ASC.
     *
     * Used by EnrollmentApplicationService to derive enrollment-level
     * progress summaries (e.g., "is this enrollment complete?").
     *
     * @return Progress[] Non-empty array only when progress records exist.
     */
    public function findByEnrollment(EnrollmentId $enrollmentId): array;

    /**
     * Save a progress record.
     *
     * @param Progress $progress The progress entity to persist.
     * @param EnrollmentId $enrollmentId The enrollment this progress belongs to
     *                                   (needed because the Progress entity
     *                                   doesn't carry its enrollment_id).
     */
    public function save(Progress $progress, EnrollmentId $enrollmentId): void;

    /**
     * Delete a progress record.
     *
     * Used by administrative operations (e.g., reset progress for a
     * re-enrollment after cancellation). Not called by the normal
     * recordProgress() flow (which only inserts/updates).
     */
    public function delete(EnrollmentId $enrollmentId, ModuleId $moduleId): void;
}
