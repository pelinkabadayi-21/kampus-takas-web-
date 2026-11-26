<?php
/**
 * İŞ KATMANI - Kayıt, giriş, çıkış ve oturum doğrulama.
 *
 * Akış:
 *  Kayıt  -> parola kurallara uyuyor mu -> password_hash() ile bcrypt özeti -> users
 *  Giriş  -> kilitli mi -> password_verify() -> rastgele oturum anahtarı üret -> SHA-256 özetini sakla
 *           -> oturum anahtarını HttpOnly çerez olarak gönder
 *  İstek  -> çerezdeki/başlıktaki oturum anahtarının özeti veritabanında var ve süresi dolmamış mı
 */
class AuthService
{
    /** Geçerli ama hiçbir parolaya karşılık gelmeyen bcrypt özeti (zamanlama saldırısına karşı) */
    private const DUMMY_HASH = '$2y$10$/G4YqGUmkYZkZmnaP1L0huGUSHnrRVU85im8sw0Sib.onu9BpBkVW';

    /** Kullanıcı ve oturum anahtarı depoları ile oturum ayarları alınır. */
    public function __construct(
        private UserRepository $users,
        private AuthTokenRepository $tokens,
        private array $cfg
    ) {}

    // ------------------------------------------------------------------ KAYIT
    public function register(array $d): array
    {
        $e = [];
        // Formdan gelen alanları al, baştaki/sondaki boşlukları temizle ($e: alan hataları burada toplanır)
        $name  = trim((string) ($d['full_name'] ?? ''));
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        $pass  = (string) ($d['password'] ?? '');
        $no    = trim((string) ($d['student_no'] ?? ''));

        // Ad soyad uzunluğu kontrolü
        if (mb_strlen($name) < 3 || mb_strlen($name) > 100)  $e['full_name'] = 'Ad soyad 3-100 karakter olmalı';
        // E-posta biçimi doğru mu, daha önce kayıtlı mı
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))      $e['email'] = 'Geçerli bir e-posta girin';
        elseif ($this->users->emailExists($email))           $e['email'] = 'Bu e-posta ile kayıtlı bir hesap var';
        // Öğrenci no girildiyse: 4-20 harf/rakam ve benzersiz olmalı
        if ($no !== '' && !preg_match('/^[A-Za-z0-9]{4,20}$/', $no)) $e['student_no'] = 'Öğrenci no 4-20 harf/rakam olmalı';
        elseif ($no !== '' && $this->users->studentNoExists($no))    $e['student_no'] = 'Bu öğrenci numarası kayıtlı';
        // Parola kurallarına uyuyor mu
        if ($msg = self::passwordProblem($pass, $email))     $e['password'] = $msg;
        // Parola tekrarı aynı mı
        if (isset($d['password_confirm']) && $d['password_confirm'] !== $pass) $e['password_confirm'] = 'Parolalar eşleşmiyor';

        // Bir tane bile hata varsa kaydetme, hataları forma geri gönder
        if ($e) throw new ValidationException('Formda hatalar var', $e);

        // Hata yoksa kullanıcıyı kaydet (parola UserRepository'de özetlenir)
        $id = $this->users->create([
            'full_name' => $name, 'email' => $email, 'password' => $pass,
            'student_no' => $no, 'department' => trim((string) ($d['department'] ?? '')),
            // Okul e-postasıyla kayıt olduysa otomatik "doğrulanmış öğrenci"
            'is_verified' => $this->isStudentEmail($email) ? 1 : 0,
            'role' => 'ogrenci',          // Kayıt olan HERKES öğrencidir; rol isteğe göre değil, yönetici atar
        ]);
        return $this->users->findById($id);
    }

    /** Parola kuralı: en az 8 karakter, harf ve rakam içermeli, e-postayla aynı olmamalı. */
    public static function passwordProblem(string $p, string $email = ''): ?string
    {
        // En az 8 karakter
        if (strlen($p) < 8)                       return 'Parola en az 8 karakter olmalı';
        if (strlen($p) > 72)                      return 'Parola en fazla 72 karakter olabilir';   // bcrypt sınırı
        // \pL = herhangi bir harf (Türkçe dahil), \d = rakam
        if (!preg_match('/\pL/u', $p) || !preg_match('/\d/', $p)) return 'Parola en az bir harf ve bir rakam içermeli';
        // Parola, e-postanın @ öncesiyle aynı olamaz (kolay tahmin edilir)
        if ($email && mb_strtolower($p) === mb_strtolower(strtok($email, '@'))) return 'Parola e-posta adınızla aynı olamaz';
        return null;
    }

    /** E-posta okulun alan adıyla mı bitiyor? (ör. @bingol.edu.tr -> doğrulanmış öğrenci) */
    private function isStudentEmail(string $email): bool
    {
        // @ işaretinden sonraki kısım: "bingol.edu.tr"
        $domain = substr(strrchr($email, '@'), 1);
        // Alan adı listedeki okul alan adlarından biriyle (veya alt alan adıyla) bitiyor mu
        foreach ($this->cfg['student_email_domains'] ?? [] as $d) {
            if ($domain === $d || str_ends_with($domain, '.' . $d)) return true;
        }
        return false;
    }

    // ------------------------------------------------------------------ GİRİŞ
    /** Başarılıysa [oturum anahtarı, kullanıcı] döner. */
    public function login(string $email, string $password, ?string $userAgent = null): array
    {
        // Şu anki zaman (saniye)
        $now  = time();
        // E-postaya göre kullanıcıyı bul (yoksa null)
        $user = $this->users->findForLogin($email);

        // Kullanıcı yoksa bile password_verify çalıştırılır: yanıt süresinden
        // "bu e-posta kayıtlı mı?" bilgisi sızmasın (zamanlama saldırısı).
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;

        // Hesap kilitliyse doğru parola girilse bile girişe izin verme (429)
        if ($user && $user['locked_until'] && strtotime($user['locked_until']) > $now) {
            // Kilidin bitmesine kaç dakika kaldı
            $mins = (int) ceil((strtotime($user['locked_until']) - $now) / 60);
            throw new ValidationException("Çok fazla hatalı deneme. $mins dakika sonra tekrar deneyin.", [], 429);
        }

        // password_verify: yazılan parolayı aynı yöntemle özetleyip kayıttaki özetle karşılaştırır
        if (!password_verify($password, $hash) || !$user) {
            if ($user) {
                // Hatalı deneme sayısını bir artır
                $count = (int) $user['failed_login_count'] + 1;
                $max   = (int) ($this->cfg['max_failed_logins'] ?? 5);
                // Sınıra ulaşıldıysa kilit bitiş zamanını hesapla (şimdi + 15 dk)
                $lock  = $count >= $max ? date('Y-m-d H:i:s', $now + 60 * (int) ($this->cfg['lock_minutes'] ?? 15)) : null;
                // Kilitlendiyse sayacı sıfırla, değilse yeni sayıyı kaydet
                $this->users->recordFailedLogin((int) $user['id'], $lock ? 0 : $count, $lock);
            }
            // Mesaj bilerek genel: "e-posta yok" ya da "parola yanlış" ayrımı yapılmaz
            // Hangisinin yanlış olduğunu söyleme (kayıtlı e-postalar ele verilmesin)
            throw new ValidationException('E-posta veya parola hatalı', [], 401);
        }

        // bcrypt maliyeti ileride artırılırsa eski özetler girişte otomatik güncellenir
        // Buraya gelindiyse parola doğru
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->users->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        // Hata sayacını sıfırla, son giriş zamanını yaz
        $this->users->recordSuccessfulLogin((int) $user['id'], date('Y-m-d H:i:s', $now));
        // Süresi dolmuş eski oturumları temizle
        $this->tokens->deleteExpired(date('Y-m-d H:i:s', $now));

        // Rastgele, tahmin edilemez 64 karakterlik oturum anahtarı üret
        $token = bin2hex(random_bytes(32));                     // 256 bit, tahmin edilemez
        // Veritabanına oturum anahtarının kendisini değil SHA-256 özetini yaz; 8 saat geçerli
        $this->tokens->create((int) $user['id'], hash('sha256', $token),
            date('Y-m-d H:i:s', $now + 3600 * (int) ($this->cfg['token_ttl_hours'] ?? 8)), $userAgent);

        // Oturum anahtarını (çereze yazılacak) ve kullanıcı bilgisini döndür
        return [$token, $this->users->findById((int) $user['id'])];
    }

    // ------------------------------------------------------------------ OTURUM
    public function userFromToken(?string $token): ?array
    {
        // Oturum anahtarı yoksa ya da biçimi yanlışsa veritabanına hiç sorma
        if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $now = date('Y-m-d H:i:s');
        // Oturum anahtarının özetini veritabanında ara; süresi dolmamış olmalı
        $row = $this->tokens->findValid(hash('sha256', $token), $now);
        if (!$row) return null;
        // Son kullanılma zamanını güncelle
        $this->tokens->touch((int) $row['token_id'], $now);
        unset($row['token_id']);
        return $row;
    }

    /** Hafta 9 – E2: Kullanıcının bildirim tercihlerini kaydeder (şimdilik sadece e-posta bildirimi açık/kapalı). */
    public function updatePreferences(int $userId, array $d): array
    {
        if (!array_key_exists('email_notifications', $d)) throw new ValidationException('Değiştirilecek tercih yok');
        // true/false, 1/0 veya "1"/"0" kabul edilir
        $this->users->update($userId, ['email_notifications' => filter_var($d['email_notifications'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0]);
        return $this->users->findById($userId);
    }

    /** Çıkış: oturum anahtarını veritabanından siler, böylece tekrar kullanılamaz. */
    public function logout(?string $token): void
    {
        if ($token) $this->tokens->deleteByHash(hash('sha256', $token));
    }
}
