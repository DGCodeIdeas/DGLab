<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Domain\Entity;
use DateTimeImmutable;
use SovereignStack\Hub\Identity\Domain\ValueObject\Email;
use SovereignStack\Hub\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
final class User
{
    /** @var RoleIdentifier[] */
    private array $roles;
    private DateTimeImmutable $updatedAt;
    public function __construct(
        private readonly UserId $id,
        private Email $email,
        private string $passwordHash,
        array $roles = [],
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->roles = $roles;
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }
    public function id(): UserId { return $this->id; }
    public function email(): Email { return $this->email; }
    public function passwordHash(): string { return $this->passwordHash; }
    /** @return RoleIdentifier[] */
    public function roles(): array { return $this->roles; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }
    public function hasRole(RoleIdentifier $role): bool
    {
        foreach ($this->roles as $r) {
            if ($r->value() === $role->value()) return true;
        }
        return false;
    }
    public function assignRole(RoleIdentifier $role): void
    {
        if (!$this->hasRole($role)) {
            $this->roles[] = $role;
            $this->updatedAt = new DateTimeImmutable();
        }
    }
}
