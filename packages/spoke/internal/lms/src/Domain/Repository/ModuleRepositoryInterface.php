<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Domain\Repository;

use SovereignStack\Spoke\Lms\Domain\Entity\Module;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, ModuleId};

/**
 * Repository contract for Module persistence.
 *
 * Per SAAI Increment 2: modules are persisted to MySQL via the lms_modules
 * table (migration 002 + migration 005 for content column).
 */
interface ModuleRepositoryInterface
{
    public function get(ModuleId $id): ?Module;

    /**
     * @return Module[] Ordered by sort_order ascending.
     */
    public function findByCourse(CourseId $courseId): array;

    public function save(Module $module): void;

    public function delete(ModuleId $id): void;

    public function deleteByCourse(CourseId $courseId): void;
}
