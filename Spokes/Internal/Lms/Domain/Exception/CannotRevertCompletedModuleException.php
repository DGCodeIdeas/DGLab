<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Lms\Domain\Exception;
use SovereignStack\Internal\Lms\Domain\ValueObject\ModuleId;
class CannotRevertCompletedModuleException extends \RuntimeException
{
    public static function forModule(ModuleId|string $id): self
    {
        return new self("Cannot revert completed module: " . (string) $id);
    }
}
