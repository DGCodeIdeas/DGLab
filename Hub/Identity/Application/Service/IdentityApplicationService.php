<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Application\Service;
use SovereignStack\Core\Crypto\PasswordHasher;
use SovereignStack\Hub\Identity\Application\AuthenticatedUser;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Hub\Identity\Domain\Entity\User;
use SovereignStack\Hub\Identity\Domain\Exception\InvalidCredentialsException;
use SovereignStack\Hub\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\Email;
use SovereignStack\Hub\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
final class IdentityApplicationService implements IdentityInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly PasswordHasher $passwordHasher,
    ) {}
    public function authenticate(string $email, string $password): AuthenticatedUser
    {
        $user = $this->users->findByEmail(Email::fromString($email))
            ?? throw InvalidCredentialsException::forUser($email);
        if (!$this->passwordHasher->verify($password, $user->passwordHash())) {
            throw InvalidCredentialsException::forUser($email);
        }
        return $this->toAuthenticatedUser($user);
    }
    public function getUserById(UserId $id): ?AuthenticatedUser
    {
        $user = $this->users->findById($id);
        return $user !== null ? $this->toAuthenticatedUser($user) : null;
    }
    public function hasRole(UserId $id, RoleIdentifier $role): bool
    {
        $user = $this->users->findById($id);
        return $user !== null && $user->hasRole($role);
    }
    public function hasAnyRole(UserId $id, RoleIdentifier ...$roles): bool
    {
        $user = $this->users->findById($id);
        if ($user === null) return false;
        foreach ($roles as $role) {
            if ($user->hasRole($role)) return true;
        }
        return false;
    }
    private function toAuthenticatedUser(User $user): AuthenticatedUser
    {
        return new AuthenticatedUser(
            id: $user->id(),
            email: $user->email(),
            roles: $user->roles(),
        );
    }
}
