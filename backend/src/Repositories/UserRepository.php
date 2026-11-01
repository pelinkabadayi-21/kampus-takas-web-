<?php
class UserRepository extends BaseRepository
{
    // password_hash bilerek listede YOK: API yanıtlarına asla parola özeti çıkmaz
    private const PUBLIC_COLS = 'id, full_name, email, student_no, department, is_verified, role, avatar_url, created_at, last_login_at';

    public function create(array $d): int
    {
        return $this->insert('users', [
            'full_name'     => $d['full_name'],
            'email'         => mb_strtolower(trim($d['email'])),
            'password_hash' => password_hash($d['password'], PASSWORD_DEFAULT),   // bcrypt + otomatik salt
            'student_no'    => ($d['student_no'] ?? '') !== '' ? $d['student_no'] : null,
            'department'    => ($d['department'] ?? '') !== '' ? $d['department'] : null,
            'is_verified'   => (int) ($d['is_verified'] ?? 0),
            'role'          => $d['role'] ?? 'ogrenci',
        ]);
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne('SELECT ' . self::PUBLIC_COLS . ' FROM users WHERE id = ?', [$id]);
    }

    /** Sadece giriş kontrolü için: parola özeti ve kilit bilgisini de döndürür. */
    public function findForLogin(string $email): ?array
    {
        return $this->fetchOne(
            'SELECT id, password_hash, failed_login_count, locked_until FROM users WHERE email = ?',
            [mb_strtolower(trim($email))]);
    }

    public function emailExists(string $email): bool
    {
        return (bool) $this->fetchOne('SELECT 1 AS x FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    public function studentNoExists(string $no): bool
    {
        return (bool) $this->fetchOne('SELECT 1 AS x FROM users WHERE student_no = ?', [$no]);
    }

    public function all(): array
    {
        return $this->fetchAll('SELECT ' . self::PUBLIC_COLS . ',
            (SELECT COUNT(*) FROM listings l WHERE l.user_id = users.id) AS listing_count
            FROM users ORDER BY created_at DESC, id DESC');
    }

    public function update(int $id, array $d): int
    {
        return $this->updateById('users', $id, $d, ['full_name', 'department', 'avatar_url', 'is_verified']);
    }

    public function setRole(int $id, string $role): int
    {
        return $this->execute('UPDATE users SET role = ? WHERE id = ?', [$role, $id]);
    }

    public function updatePasswordHash(int $id, string $hash): int
    {
        return $this->execute('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $id]);
    }

    public function recordFailedLogin(int $id, int $count, ?string $lockedUntil): void
    {
        $this->execute('UPDATE users SET failed_login_count = ?, locked_until = ? WHERE id = ?', [$count, $lockedUntil, $id]);
    }

    public function recordSuccessfulLogin(int $id, string $now): void
    {
        $this->execute('UPDATE users SET failed_login_count = 0, locked_until = NULL, last_login_at = ? WHERE id = ?', [$now, $id]);
    }

    public function delete(int $id): int
    {
        return $this->execute('DELETE FROM users WHERE id = ?', [$id]);
    }
}
