<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Application\Command;

use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Spoke\Lms\Domain\ValueObject\EnrollmentId;

/**
 * Command to cancel an enrollment.
 *
 * Per SAAI Increment 3 directive: cancellation is authorized for:
 *   - The learner themselves (self-cancellation — fundamental exitability right)
 *   - An administrator (lms:admin, platform:admin) — operational moderation
 *
 * The application service enforces this via IdentityInterface::hasAnyRole()
 * OR by direct ownership check (enrollment->learnerId()->equals($cancelledBy)).
 */
final readonly class CancelEnrollmentCommand
{
    public function __construct(
        public EnrollmentId $enrollmentId,
        public UserId $cancelledBy,
    ) {
    }
}
