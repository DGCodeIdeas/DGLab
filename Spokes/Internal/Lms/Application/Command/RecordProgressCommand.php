<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Lms\Application\Command;

use SovereignStack\Internal\Lms\Domain\ValueObject\{EnrollmentId, ModuleId, ModuleStatus};

final readonly class RecordProgressCommand
{
    public function __construct(
        public EnrollmentId $enrollmentId,
        public ModuleId $moduleId,
        public ModuleStatus $newStatus,
    ) {
    }
}