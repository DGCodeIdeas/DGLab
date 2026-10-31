<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Lms\Domain\Exception;

use SovereignStack\Internal\Lms\Domain\ValueObject\EnrollmentId;

/**
 * Thrown when an enrollment is not found by ID.
 *
 * Distinct from CourseNotFoundException (which is about the course, not the
 * enrollment). Added in Increment 3 PR-A1 alongside the EnrollmentApplicationService,
 * since cancelEnrollment() and getEnrollment() both need a clear "enrollment
 * not found" exception type that accepts an EnrollmentId (not a CourseId).
 */
class EnrollmentNotFoundException extends \RuntimeException
{
    public static function forId(EnrollmentId|string $id): self
    {
        $idStr = is_string($id) ? $id : (string) $id;
        return new self("Enrollment not found: {$idStr}", 404);
    }
}
