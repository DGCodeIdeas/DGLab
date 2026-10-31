<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Lms\Domain\ValueObject;
enum ModuleStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
