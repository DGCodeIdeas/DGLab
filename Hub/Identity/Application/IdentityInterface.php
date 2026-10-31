<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Application;
use SovereignStack\Hub\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
interface IdentityInterface
{
    public function authenticate(string $email, string $password): AuthenticatedUser;
    public function getUserById(UserId $id): ?AuthenticatedUser;
    public function hasRole(UserId $id, RoleIdentifier $role): bool;
    public function hasAnyRole(UserId $id, RoleIdentifier ...$roles): bool;
}
