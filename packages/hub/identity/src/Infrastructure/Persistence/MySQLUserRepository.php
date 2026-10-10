<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Identity\Infrastructure\Persistence;

use DateTimeImmutable;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Domain\Entity\User;
use SovereignStack\Hub\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\{Email, RoleIdentifier, UserId};

/**
 * MySQL implementation of UserRepositoryInterface.
 *
 * Uses the actual ConnectionInterface API (prepare/execute/fetch) — NOT
 * fetchOne()/execute()/fetchAll() which do not exist on the shipped
 * core-dbal ConnectionInterface.
 *
 * Fixed in Increment 3 Step 1: DBAL contract drift (same fix pattern
 * as LMS PR #355). Per SAAI: "Leave ConnectionInterface unchanged."
 */
final class MySQLUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {
    }

    public function findById(UserId $id): ?User
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM users WHERE id = :id',
        );
        $stmt->execute(['id' => (string) $id]);
        /** @var array<string, string|null>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $roles = $this->fetchRoles($id);

        return $this->hydrate($row, $roles);
    }

    public function findByEmail(Email $email): ?User
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM users WHERE email = :email',
        );
        $stmt->execute(['email' => (string) $email]);
        /** @var array<string, string|null>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $userId = new UserId((string) $row['id']);
        $roles = $this->fetchRoles($userId);

        return $this->hydrate($row, $roles);
    }

    public function save(User $user): void
    {
        $this->connection->beginTransaction();
        try {
            $this->saveInternal($user);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function delete(UserId $id): void
    {
        $stmt = $this->connection->prepare('DELETE FROM user_roles WHERE user_id = :id');
        $stmt->execute(['id' => (string) $id]);

        $stmt = $this->connection->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => (string) $id]);
    }

    private function saveInternal(User $user): void
    {
        // Check if user exists
        $stmt = $this->connection->prepare('SELECT id FROM users WHERE id = :id');
        $stmt->execute(['id' => (string) $user->id()]);
        /** @var array<string, string|null>|false $existing */
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        $data = [
            'id' => (string) $user->id(),
            'email' => (string) $user->email(),
            'hash' => $user->passwordHash(),
        ];

        if ($existing !== false) {
            $stmt = $this->connection->prepare(
                'UPDATE users SET email = :email, password_hash = :hash, updated_at = NOW() WHERE id = :id',
            );
        } else {
            $stmt = $this->connection->prepare(
                'INSERT INTO users (id, email, password_hash, created_at, updated_at) VALUES (:id, :email, :hash, NOW(), NOW())',
            );
        }
        $stmt->execute($data);

        // Delete existing roles
        $stmt = $this->connection->prepare('DELETE FROM user_roles WHERE user_id = :id');
        $stmt->execute(['id' => (string) $user->id()]);

        // Insert roles
        foreach ($user->roles() as $role) {
            $roleId = $this->ensureRole($role);
            $stmt = $this->connection->prepare(
                'INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)',
            );
            $stmt->execute(['uid' => (string) $user->id(), 'rid' => $roleId]);
        }
    }

    private function ensureRole(RoleIdentifier $role): string
    {
        $stmt = $this->connection->prepare('SELECT id FROM roles WHERE name = :name');
        $stmt->execute(['name' => $role->value()]);
        /** @var array<string, string|null>|false $existing */
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing !== false) {
            return (string) $existing['id'];
        }

        $roleId = bin2hex(random_bytes(13));
        $stmt = $this->connection->prepare(
            'INSERT INTO roles (id, name, description, created_at) VALUES (:id, :name, :desc, NOW())',
        );
        $stmt->execute([
            'id' => $roleId,
            'name' => $role->value(),
            'desc' => $role->value(),
        ]);

        return $roleId;
    }

    /**
     * @return RoleIdentifier[]
     */
    private function fetchRoles(UserId $userId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :uid',
        );
        $stmt->execute(['uid' => (string) $userId]);
        /** @var array<int, array<string, string|null>> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $roles = [];
        foreach ($rows as $row) {
            $roles[] = RoleIdentifier::fromString((string) $row['name']);
        }

        return $roles;
    }

    /**
     * @param array<string, string|null> $row
     * @param RoleIdentifier[] $roles
     */
    private function hydrate(array $row, array $roles): User
    {
        return new User(
            id: new UserId((string) $row['id']),
            email: Email::fromString((string) $row['email']),
            passwordHash: (string) $row['password_hash'],
            roles: $roles,
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: new DateTimeImmutable((string) ($row['updated_at'] ?? $row['created_at'])),
        );
    }
}
