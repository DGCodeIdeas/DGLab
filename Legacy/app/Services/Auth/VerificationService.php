<?php

namespace DGLab\Services\Auth;

use DGLab\Models\User;
use DGLab\Database\Connection;

class VerificationService
{
    public function createToken(User $user, string $type, int $expiresInMinutes = 60): string
    {
        $token = bin2hex(random_bytes(40));
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiresInMinutes * 60));

        Connection::getInstance()->insert(
            "INSERT INTO user_verifications (user_id, token, type, expires_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
            [$user->id, $token, $type, $expiresAt, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );

        return $token;
    }

    public function verifyToken(string $token, string $type): ?User
    {
        $record = Connection::getInstance()->selectOne(
            "SELECT * FROM user_verifications WHERE token = ? AND type = ? AND expires_at > ?",
            [$token, $type, date('Y-m-d H:i:s')]
        );

        if (!$record) {
            return null;
        }

        $user = User::find($record['user_id']);
        if ($user) {
            // Delete token after successful verification
            Connection::getInstance()->delete("DELETE FROM user_verifications WHERE id = ?", [$record['id']]);
        }

        return $user;
    }
}
