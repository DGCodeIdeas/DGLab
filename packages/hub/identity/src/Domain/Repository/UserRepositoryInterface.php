<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Domain\Repository;
use SovereignStack\Hub\Identity\Domain\Entity\User;
use SovereignStack\Hub\Identity\Domain\ValueObject\Email;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
interface UserRepositoryInterface
{
    public function findById(UserId $id): ?User;
    public function findByEmail(Email $email): ?User;
    public function save(User $user): void;
    public function delete(UserId $id): void;
}
