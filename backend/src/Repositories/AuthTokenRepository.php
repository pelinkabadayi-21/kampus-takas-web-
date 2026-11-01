<?php
class AuthTokenRepository extends BaseRepository
{
    public function create(int $userId, string $tokenHash, string $expiresAt, ?string $userAgent): int
    {
        return $this->insert('auth_tokens', [
            'user_id' => $userId, 'token_hash' => $tokenHash,
            'expires_at' => $expiresAt, 'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
        ]);
    }

    /** Süresi dolmamış token'ı ve sahibini getirir. */
    public function findValid(string $tokenHash, string $now): ?array
    {
        return $this->fetchOne(
            'SELECT t.id AS token_id, t.expires_at, u.id, u.full_name, u.email, u.role, u.is_verified, u.department
             FROM auth_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.expires_at > ?', [$tokenHash, $now]);
    }

    public function touch(int $tokenId, string $now): void
    {
        $this->execute('UPDATE auth_tokens SET last_used_at = ? WHERE id = ?', [$now, $tokenId]);
    }

    public function deleteByHash(string $tokenHash): int
    {
        return $this->execute('DELETE FROM auth_tokens WHERE token_hash = ?', [$tokenHash]);
    }

    /** Tüm cihazlardan çıkış (ör. parola değişince) */
    public function deleteAllForUser(int $userId): int
    {
        return $this->execute('DELETE FROM auth_tokens WHERE user_id = ?', [$userId]);
    }

    public function deleteExpired(string $now): int
    {
        return $this->execute('DELETE FROM auth_tokens WHERE expires_at <= ?', [$now]);
    }
}
