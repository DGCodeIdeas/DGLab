<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Lms\Domain\ValueObject;
enum EnrollmentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
