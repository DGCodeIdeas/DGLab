<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Infrastructure\Persistence;
use DateTimeImmutable;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Domain\Entity\User;
use SovereignStack\Hub\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\Email;
use SovereignStack\Hub\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
final class MySQLUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {}
    public function findById(UserId $id): ?User
    {
        $row = $this->connection->fetchOne(
            'SELECT * FROM users WHERE id = :id',
            ['id' => (string) $id]
        );
        if ($row === null) return null;
        $roles = $this->fetchRoles($id);
        return $this->hydrate($row, $roles);
    }
    public function findByEmail(Email $email): ?User
    {
        $row = $this->connection->fetchOne(
            'SELECT * FROM users WHERE email = :email',
            ['email' => (string) $email]
        );
        if ($row === null) return null;
        $userId = new UserId($row['id']);
        $roles = $this->fetchRoles($userId);
        return $this->hydrate($row, $roles);
    }
    public function save(User $user): void
    {
        $exists = $this->connection->fetchOne(
            'SELECT id FROM users WHERE id = :id',
            ['id' => (string) $user->id()]
        ) !== null;
        if ($exists) {
            $this->connection->execute(
                'UPDATE users SET email = :email, password_hash = :hash, updated_at = NOW() WHERE id = :id',
                ['email' => (string) $user->email(), 'hash' => $user->passwordHash(), 'id' => (string) $user->id()]
            );
        } else {
            $this->connection->execute(
                'INSERT INTO users (id, email, password_hash, created_at, updated_at) VALUES (:id, :email, :hash, NOW(), NOW())',
                ['id' => (string) $user->id(), 'email' => (string) $user->email(), 'hash' => $user->passwordHash()]
            );
        }
        $this->connection->execute('DELETE FROM user_roles WHERE user_id = :id', ['id' => (string) $user->id()]);
        foreach ($user->roles() as $role) {
            $roleId = $this->ensureRole($role);
            $this->connection->execute(
                'INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)',
                ['uid' => (string) $user->id(), 'rid' => $roleId]
            );
        }
    }
    public function delete(UserId $id): void
    {
        $this->connection->execute('DELETE FROM user_roles WHERE user_id = :id', ['id' => (string) $id]);
        $this->connection->execute('DELETE FROM users WHERE id = :id', ['id' => (string) $id]);
    }
    private function ensureRole(RoleIdentifier $role): string
    {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM roles WHERE name = :name',
            ['name' => $role->value()]
        );
        if ($existing !== null) return $existing['id'];
        $roleId = bin2hex(random_bytes(13));
        $this->connection->execute(
            'INSERT INTO roles (id, name, description, created_at) VALUES (:id, :name, :desc, NOW())',
            ['id' => $roleId, 'name' => $role->value(), 'desc' => $role->value()]
        );
        return $roleId;
    }
    /** @return RoleIdentifier[] */
    private function fetchRoles(UserId $userId): array
    {
        $rows = $this->connection->fetchAll(
            'SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :uid',
            ['uid' => (string) $userId]
        );
        $roles = [];
        foreach ($rows as $row) {
            $roles[] = RoleIdentifier::fromString($row['name']);
        }
        return $roles;
    }
    /** @param array<string, mixed> $row @param RoleIdentifier[] $roles */
    private function hydrate(array $row, array $roles): User
    {
        return new User(
            id: new UserId($row['id']),
            email: Email::fromString($row['email']),
            passwordHash: $row['password_hash'],
            roles: $roles,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
