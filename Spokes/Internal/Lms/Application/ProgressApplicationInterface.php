<?php

declare(strict_types=1);
namespace SovereignStack\Internal\Lms\Application;
use SovereignStack\Internal\Lms\Domain\Entity\Progress;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Internal\Lms\Domain\ValueObject\{EnrollmentId, ModuleId};
use SovereignStack\Internal\Lms\Application\Command\RecordProgressCommand;

interface ProgressApplicationInterface
{
    public function recordProgress(RecordProgressCommand $cmd, UserId $actor): void;

    public function getProgress(EnrollmentId $enrollmentId, ModuleId $moduleId, UserId $actor): ?Progress;

    public function listProgressForEnrollment(EnrollmentId $enrollmentId, UserId $actor): array;
}