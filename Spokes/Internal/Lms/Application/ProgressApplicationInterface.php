<?php

declare(strict_types=1);
namespace SovereignStack\Internal\Lms\Application;
use SovereignStack\Internal\Lms\Domain\Entity\Progress;
use SovereignStack\Internal\Lms\Domain\ValueObject\{EnrollmentId, ModuleId};

interface ProgressApplicationInterface
{
    public function recordProgress(RecordProgressCommand $cmd, UserId $actor): void;

    public function getProgress(EnrollmentId $enrollmentId, ModuleId $moduleId, UserId $actor): ?Progress;

    public function listProgressForEnrollment(EnrollmentId $enrollmentId, UserId $actor): array;
}