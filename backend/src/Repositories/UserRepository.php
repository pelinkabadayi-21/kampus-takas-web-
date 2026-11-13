<?php
class UserRepository extends BaseRepository
{
    // password_hash bilerek listede YOK: API yanıtlarına asla parola özeti çıkmaz
    // Dışarıya gösterilecek sütunlar; password_hash bilerek yok
    private const PUBLIC_COLS = 'id, full_name, email, student_no, department, is_verified, role, avatar_url, created_at, last_login_at';

    /** Yeni kullanıcı ekler; parola password_hash ile özetlenerek kaydedilir. */
    public function create(array $d): int
    {
        return $this->insert('users', [
            'full_name'     => $d['full_name'],
            // E-postayı boşluksuz ve küçük harfle sakla (Ali@X.com = ali@x.com)
            'email'         => mb_strtolower(trim($d['email'])),
            'password_hash' => password_hash($d['password'], PASSWORD_DEFAULT),   // bcrypt + otomatik salt
            // Boş bırakılan alanları NULL olarak kaydet
            'student_no'    => ($d['student_no'] ?? '') !== '' ? $d['student_no'] : null,
            'department'    => ($d['department'] ?? '') !== '' ? $d['department'] : null,
            'is_verified'   => (int) ($d['is_verified'] ?? 0),
            // Rol verilmediyse öğrenci
            'role'          => $d['role'] ?? 'ogrenci',
        ]);
    }

    /** id'ye göre kullanıcıyı getirir (parola özeti hariç). */
    public function findById(int $id): ?array
    {
        // ? yer tutucusu: id değeri SQL'e ayrı gönderilir (SQL injection olmaz)
        return $this->fetchOne('SELECT ' . self::PUBLIC_COLS . ' FROM users WHERE id = ?', [$id]);
    }

    /** Sadece giriş kontrolü için: parola özeti ve kilit bilgisini de döndürür. */
    public function findForLogin(string $email): ?array
    {
        return $this->fetchOne(
            // Girişte parola özeti ve kilit bilgisi de lazım
            'SELECT id, password_hash, failed_login_count, locked_until FROM users WHERE email = ?',
            [mb_strtolower(trim($email))]);
    }

    /** Bu e-posta ile kayıtlı kullanıcı var mı? */
    public function emailExists(string $email): bool
    {
        // Satır bulunursa true, bulunmazsa false
        return (bool) $this->fetchOne('SELECT 1 AS x FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    /** Bu öğrenci numarası kayıtlı mı? */
    public function studentNoExists(string $no): bool
    {
        return (bool) $this->fetchOne('SELECT 1 AS x FROM users WHERE student_no = ?', [$no]);
    }

    /** Bütün kullanıcılar ve ilan sayıları (yönetim paneli için). */
    public function all(): array
    {
        // Her kullanıcı için ilan sayısını da alt sorguyla hesapla; en yeni kayıt en üstte
        return $this->fetchAll('SELECT ' . self::PUBLIC_COLS . ',
            (SELECT COUNT(*) FROM listings l WHERE l.user_id = users.id) AS listing_count
            FROM users ORDER BY created_at DESC, id DESC');
    }

    /** Kullanıcının izin verilen alanlarını günceller. */
    public function update(int $id, array $d): int
    {
        // Sadece bu 4 alan güncellenebilir (rol ve e-posta buradan değişmez)
        return $this->updateById('users', $id, $d, ['full_name', 'department', 'avatar_url', 'is_verified']);
    }

    /** Kullanıcının rolünü değiştirir (ogrenci / yonetici). */
    public function setRole(int $id, string $role): int
    {
        return $this->execute('UPDATE users SET role = ? WHERE id = ?', [$role, $id]);
    }

    /** Parola özetini günceller. */
    public function updatePasswordHash(int $id, string $hash): int
    {
        return $this->execute('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $id]);
    }

    /** Hatalı giriş sayısını ve kilit süresini kaydeder. */
    public function recordFailedLogin(int $id, int $count, ?string $lockedUntil): void
    {
        // Hata sayısını artır; sınır aşıldıysa kilit bitiş zamanını yaz
        $this->execute('UPDATE users SET failed_login_count = ?, locked_until = ? WHERE id = ?', [$count, $lockedUntil, $id]);
    }

    /** Başarılı girişte hata sayacını sıfırlar, kilidi kaldırır, giriş zamanını yazar. */
    public function recordSuccessfulLogin(int $id, string $now): void
    {
        // Başarılı giriş: sayaç sıfır, kilit yok, son giriş zamanı güncel
        $this->execute('UPDATE users SET failed_login_count = 0, locked_until = NULL, last_login_at = ? WHERE id = ?', [$now, $id]);
    }

    /** Yönetici paneli istatistikleri (Hafta 5 öğrenci bölümü) */
    public function stats(): array
    {
        // Küçük yardımcı: COUNT sorgusunu çalıştırıp sayıyı döndürür
        $one = fn(string $sql) => (int) $this->fetchOne($sql)['n'];
        return [
            'users'            => $one('SELECT COUNT(*) AS n FROM users'),
            'verified_users'   => $one('SELECT COUNT(*) AS n FROM users WHERE is_verified = 1'),
            'admins'           => $one("SELECT COUNT(*) AS n FROM users WHERE role = 'yonetici'"),
            'active_listings'  => $one("SELECT COUNT(*) AS n FROM listings WHERE status = 'aktif'"),
            'pending_offers'   => $one("SELECT COUNT(*) AS n FROM trade_offers WHERE status = 'beklemede'"),
        ];
    }

    /** Kullanıcıyı siler (ilanları vb. CASCADE ile silinir). */
    public function delete(int $id): int
    {
        return $this->execute('DELETE FROM users WHERE id = ?', [$id]);
    }
}
