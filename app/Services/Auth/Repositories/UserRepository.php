<?php

namespace DGLab\Services\Auth\Repositories;

use DGLab\Models\User;
use DGLab\Services\Auth\UUIDService;
use InvalidArgumentException;

/**
 * User Repository
 *
 * Handles database operations for the User model.
 */
class UserRepository
{
    private UUIDService $uuidService;

    public function __construct(?UUIDService $uuidService = null)
    {
        $this->uuidService = $uuidService ?? new UUIDService();
    }

    /**
     * Find a user by ID
     *
     * @param int $id
     * @return User|null
     */
    public function find(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Find a user by UUID
     *
     * @param string $uuid
     * @return User|null
     */
    public function findByUuid(string $uuid): ?User
    {
        return User::findBy(['uuid' => $uuid]);
    }

    /**
     * Find a user by any of the identifiers (email, username, phone)
     *
     * @param string $identifier
     * @return User|null
     */
    public function findByIdentifier(string $identifier): ?User
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::findBy(['email' => $identifier]);
        }

        $usernamePattern = config('auth.validation.username', '/^[a-zA-Z0-9_-]{3,100}$/');
        if (preg_match($usernamePattern, $identifier)) {
             $user = User::findBy(['username' => $identifier]);
            if ($user) {
                return $user;
            }
        }

        $phonePattern = config('auth.validation.phone', '/^\+?[0-9]{7,20}$/');
        if (preg_match($phonePattern, $identifier)) {
             return User::findBy(['phone_number' => $identifier]);
        }

        return null;
    }

    /**
     * Create a new user
     *
     * @param array $data
     * @return User
     * @throws InvalidArgumentException
     */
    public function create(array $data): User
    {
        if (!isset($data['uuid'])) {
            $data['uuid'] = $this->uuidService->generate();
        }

        $this->validateIdentifiers($data);

        return User::create($data);
    }

    /**
     * Validate user identifiers
     *
     * @param array $data
     * @throws InvalidArgumentException
     */
    protected function validateIdentifiers(array $data): void
    {
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email format.");
        }

        if (isset($data['username'])) {
            $pattern = config('auth.validation.username', '/^[a-zA-Z0-9_-]{3,100}$/');
            if (!preg_match($pattern, $data['username'])) {
                throw new InvalidArgumentException("Invalid username format.");
            }
        }

        if (isset($data['phone_number'])) {
            $pattern = config('auth.validation.phone', '/^\+?[0-9]{7,20}$/');
            if (!preg_match($pattern, $data['phone_number'])) {
                throw new InvalidArgumentException("Invalid phone number format.");
            }
        }
    }
}
