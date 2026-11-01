<?php
class UserRepository extends BaseRepository
{
    private const PUBLIC_COLS = 'id, full_name, email, student_no, department, is_verified, avatar_url, created_at';

    public function create(array $d): int
    {
        return $this->insert('users', [
            'full_name'     => $d['full_name'],
            'email'         => mb_strtolower($d['email']),
            'password_hash' => password_hash($d['password'], PASSWORD_DEFAULT),
            'student_no'    => $d['student_no'] ?? null,
            'department'    => $d['department'] ?? null,
            'is_verified'   => (int) ($d['is_verified'] ?? 0),
        ]);
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne('SELECT ' . self::PUBLIC_COLS . ' FROM users WHERE id = ?', [$id]);
    }

    /** Giriş için: şifre özetini de döndürür. */
    public function findByEmailWithPassword(string $email): ?array
    {
        return $this->fetchOne('SELECT * FROM users WHERE email = ?', [mb_strtolower($email)]);
    }

    public function update(int $id, array $d): int
    {
        return $this->updateById('users', $id, $d, ['full_name', 'department', 'avatar_url', 'is_verified']);
    }

    public function delete(int $id): int
    {
        return $this->execute('DELETE FROM users WHERE id = ?', [$id]);
    }
}
