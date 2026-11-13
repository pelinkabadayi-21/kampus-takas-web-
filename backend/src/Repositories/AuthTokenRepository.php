<?php
/** VERİ KATMANI - Oturum anahtarlarının veritabanı işlemleri. Oturum anahtarının kendisi değil SHA-256 özeti saklanır. */
class AuthTokenRepository extends BaseRepository
{
    /** Yeni oturum kaydı ekler (oturum anahtarı özeti, son kullanma zamanı, tarayıcı bilgisi). */
    public function create(int $userId, string $tokenHash, string $expiresAt, ?string $userAgent): int
    {
        // Oturum anahtarının kendisi değil SHA-256 özeti saklanır; tarayıcı bilgisi 255 karaktere kısaltılır
        return $this->insert('auth_tokens', [
            'user_id' => $userId, 'token_hash' => $tokenHash,
            'expires_at' => $expiresAt, 'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
        ]);
    }

    /** Süresi dolmamış oturum anahtarını ve sahibini getirir. */
    public function findValid(string $tokenHash, string $now): ?array
    {
        // Özeti eşleşen ve süresi dolmamış oturum anahtarının kullanıcısını getir
        return $this->fetchOne(
            'SELECT t.id AS token_id, t.expires_at, u.id, u.full_name, u.email, u.role, u.is_verified, u.department
             FROM auth_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.expires_at > ?', [$tokenHash, $now]);
    }

    /** Oturum anahtarının son kullanılma zamanını günceller. */
    public function touch(int $tokenId, string $now): void
    {
        $this->execute('UPDATE auth_tokens SET last_used_at = ? WHERE id = ?', [$now, $tokenId]);
    }

    /** Çıkış yapınca o oturum anahtarını siler. */
    public function deleteByHash(string $tokenHash): int
    {
        return $this->execute('DELETE FROM auth_tokens WHERE token_hash = ?', [$tokenHash]);
    }

    /** Tüm cihazlardan çıkış (ör. parola değişince) */
    public function deleteAllForUser(int $userId): int
    {
        return $this->execute('DELETE FROM auth_tokens WHERE user_id = ?', [$userId]);
    }

    /** Süresi dolmuş bütün oturum anahtarlarını temizler. */
    public function deleteExpired(string $now): int
    {
        return $this->execute('DELETE FROM auth_tokens WHERE expires_at <= ?', [$now]);
    }
}
