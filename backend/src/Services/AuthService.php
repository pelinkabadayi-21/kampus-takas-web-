<?php
/**
 * İŞ KATMANI - Kayıt, giriş, çıkış ve oturum doğrulama.
 *
 * Akış:
 *  Kayıt  -> parola kurallara uyuyor mu -> password_hash() ile bcrypt özeti -> users
 *  Giriş  -> kilitli mi -> password_verify() -> rastgele token üret -> SHA-256 özetini sakla
 *           -> token'ı HttpOnly çerez olarak gönder
 *  İstek  -> çerezdeki/başlıktaki token'ın özeti veritabanında var ve süresi dolmamış mı
 */
class AuthService
{
    /** Geçerli ama hiçbir parolaya karşılık gelmeyen bcrypt özeti (zamanlama saldırısına karşı) */
    private const DUMMY_HASH = '$2y$10$/G4YqGUmkYZkZmnaP1L0huGUSHnrRVU85im8sw0Sib.onu9BpBkVW';

    public function __construct(
        private UserRepository $users,
        private AuthTokenRepository $tokens,
        private array $cfg
    ) {}

    // ------------------------------------------------------------------ KAYIT
    public function register(array $d): array
    {
        $e = [];
        $name  = trim((string) ($d['full_name'] ?? ''));
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        $pass  = (string) ($d['password'] ?? '');
        $no    = trim((string) ($d['student_no'] ?? ''));

        if (mb_strlen($name) < 3 || mb_strlen($name) > 100)  $e['full_name'] = 'Ad soyad 3-100 karakter olmalı';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))      $e['email'] = 'Geçerli bir e-posta girin';
        elseif ($this->users->emailExists($email))           $e['email'] = 'Bu e-posta ile kayıtlı bir hesap var';
        if ($no !== '' && !preg_match('/^[A-Za-z0-9]{4,20}$/', $no)) $e['student_no'] = 'Öğrenci no 4-20 harf/rakam olmalı';
        elseif ($no !== '' && $this->users->studentNoExists($no))    $e['student_no'] = 'Bu öğrenci numarası kayıtlı';
        if ($msg = self::passwordProblem($pass, $email))     $e['password'] = $msg;
        if (isset($d['password_confirm']) && $d['password_confirm'] !== $pass) $e['password_confirm'] = 'Parolalar eşleşmiyor';

        if ($e) throw new ValidationException('Formda hatalar var', $e);

        $id = $this->users->create([
            'full_name' => $name, 'email' => $email, 'password' => $pass,
            'student_no' => $no, 'department' => trim((string) ($d['department'] ?? '')),
            'is_verified' => $this->isStudentEmail($email) ? 1 : 0,
            'role' => 'ogrenci',          // Kayıt olan HERKES öğrencidir; rol isteğe göre değil, yönetici atar
        ]);
        return $this->users->findById($id);
    }

    /** Parola kuralı: en az 8 karakter, harf ve rakam içermeli, e-postayla aynı olmamalı. */
    public static function passwordProblem(string $p, string $email = ''): ?string
    {
        if (strlen($p) < 8)                       return 'Parola en az 8 karakter olmalı';
        if (strlen($p) > 72)                      return 'Parola en fazla 72 karakter olabilir';   // bcrypt sınırı
        if (!preg_match('/\pL/u', $p) || !preg_match('/\d/', $p)) return 'Parola en az bir harf ve bir rakam içermeli';
        if ($email && mb_strtolower($p) === mb_strtolower(strtok($email, '@'))) return 'Parola e-posta adınızla aynı olamaz';
        return null;
    }

    private function isStudentEmail(string $email): bool
    {
        $domain = substr(strrchr($email, '@'), 1);
        foreach ($this->cfg['student_email_domains'] ?? [] as $d) {
            if ($domain === $d || str_ends_with($domain, '.' . $d)) return true;
        }
        return false;
    }

    // ------------------------------------------------------------------ GİRİŞ
    /** Başarılıysa [token, user] döner. */
    public function login(string $email, string $password, ?string $userAgent = null): array
    {
        $now  = time();
        $user = $this->users->findForLogin($email);

        // Kullanıcı yoksa bile password_verify çalıştırılır: yanıt süresinden
        // "bu e-posta kayıtlı mı?" bilgisi sızmasın (zamanlama saldırısı).
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;

        if ($user && $user['locked_until'] && strtotime($user['locked_until']) > $now) {
            $mins = (int) ceil((strtotime($user['locked_until']) - $now) / 60);
            throw new ValidationException("Çok fazla hatalı deneme. $mins dakika sonra tekrar deneyin.", [], 429);
        }

        if (!password_verify($password, $hash) || !$user) {
            if ($user) {
                $count = (int) $user['failed_login_count'] + 1;
                $max   = (int) ($this->cfg['max_failed_logins'] ?? 5);
                $lock  = $count >= $max ? date('Y-m-d H:i:s', $now + 60 * (int) ($this->cfg['lock_minutes'] ?? 15)) : null;
                $this->users->recordFailedLogin((int) $user['id'], $lock ? 0 : $count, $lock);
            }
            // Mesaj bilerek genel: "e-posta yok" ya da "parola yanlış" ayrımı yapılmaz
            throw new ValidationException('E-posta veya parola hatalı', [], 401);
        }

        // bcrypt maliyeti ileride artırılırsa eski özetler girişte otomatik güncellenir
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->users->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        $this->users->recordSuccessfulLogin((int) $user['id'], date('Y-m-d H:i:s', $now));
        $this->tokens->deleteExpired(date('Y-m-d H:i:s', $now));

        $token = bin2hex(random_bytes(32));                     // 256 bit, tahmin edilemez
        $this->tokens->create((int) $user['id'], hash('sha256', $token),
            date('Y-m-d H:i:s', $now + 3600 * (int) ($this->cfg['token_ttl_hours'] ?? 8)), $userAgent);

        return [$token, $this->users->findById((int) $user['id'])];
    }

    // ------------------------------------------------------------------ OTURUM
    public function userFromToken(?string $token): ?array
    {
        if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $now = date('Y-m-d H:i:s');
        $row = $this->tokens->findValid(hash('sha256', $token), $now);
        if (!$row) return null;
        $this->tokens->touch((int) $row['token_id'], $now);
        unset($row['token_id']);
        return $row;
    }

    public function logout(?string $token): void
    {
        if ($token) $this->tokens->deleteByHash(hash('sha256', $token));
    }
}
