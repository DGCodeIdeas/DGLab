<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Application;
use SovereignStack\Hub\Identity\Domain\ValueObject\Email;
use SovereignStack\Hub\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
final readonly class AuthenticatedUser
{
    /** @param RoleIdentifier[] $roles */
    public function __construct(
        public UserId $id,
        public Email $email,
        public array $roles,
    ) {}
    public function hasRole(RoleIdentifier $role): bool
    {
        foreach ($this->roles as $r) {
            if ($r->value() === $role->value()) return true;
        }
        return false;
    }
    public function hasAnyRole(RoleIdentifier ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) return true;
        }
        return false;
    }
}
