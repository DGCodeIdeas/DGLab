<?php

declare(strict_types=1);
namespace SovereignStack\Internal\Lms\Domain\Repository;
use SovereignStack\Internal\Lms\Domain\Entity\Progress;
use SovereignStack\Internal\Lms\Domain\ValueObject\{EnrollmentId, ModuleId};

interface ProgressRepositoryInterface
{
    public function get(EnrollmentId $enrollmentId, ModuleId $moduleId): ?Progress;

    public function getForUpdate(EnrollmentId $enrollmentId, ModuleId $moduleId): ?Progress;

    public function findByEnrollment(EnrollmentId $enrollmentId): array;

    public function insert(Progress $progress, EnrollmentId $enrollmentId): void;

    public function save(Progress $progress, EnrollmentId $enrollmentId): void;

    public function delete(EnrollmentId $enrollmentId, ModuleId $moduleId): void;
}