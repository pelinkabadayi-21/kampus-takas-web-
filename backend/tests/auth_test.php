<?php
/**
 * KİMLİK DOĞRULAMA TESTİ - Kayıt, parola hash, giriş, token, kilit ve rol kontrollerini dener.
 * Kendi test kullanıcısını oluşturur ve sonunda siler.
 * Kullanım: php backend/tests/auth_test.php
 */
if (PHP_SAPI !== 'cli') exit('Sadece komut satırından çalışır.');
require __DIR__ . '/../src/bootstrap.php';
$root   = dirname(__DIR__);
$config = file_exists("$root/config/config.php") ? require "$root/config/config.php" : require "$root/config/config.example.php";
$pdo    = Database::connect($config['db']);
date_default_timezone_set('Europe/Istanbul');

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail) { echo ($ok ? "  ✔ " : "  ✘ ") . $name . PHP_EOL; if (!$ok) $fail++; };
$throws = function (callable $fn, int $code) { try { $fn(); return false; } catch (ValidationException $e) { return $e->getCode() === $code; } };

$users  = new UserRepository($pdo);
$tokens = new AuthTokenRepository($pdo);
$auth   = new AuthService($users, $tokens, ['token_ttl_hours' => 8, 'max_failed_logins' => 3, 'lock_minutes' => 15,
                                            'student_email_domains' => ['ogrenci.example.com']]);
$email  = 'authtest' . time() . '@ogrenci.example.com';
$id = null;

try {
    echo "KAYIT\n";
    $u = $auth->register(['full_name' => 'Auth Test', 'email' => $email, 'password' => 'Parola123', 'role' => 'yonetici']);
    $id = (int) $u['id'];
    $hash = $pdo->query("SELECT password_hash FROM users WHERE id = $id")->fetchColumn();
    $check('Parola düz metin değil, bcrypt özeti: ' . substr($hash, 0, 7) . '…', str_starts_with($hash, '$2y$'));
    $check('Kayıtta rol gönderilse bile "ogrenci" oldu', $u['role'] === 'ogrenci');
    $check('Okul e-postası -> doğrulanmış öğrenci', (int) $u['is_verified'] === 1);
    $check('Zayıf parola reddedildi', AuthService::passwordProblem('12345678') !== null);

    echo "GİRİŞ / TOKEN\n";
    [$token] = $auth->login($email, 'Parola123');
    $check('Giriş başarılı, token üretildi', strlen($token) === 64);
    $check('Veritabanında token değil özeti var', (bool) $tokens->findValid(hash('sha256', $token), date('Y-m-d H:i:s')));
    $check('Token ile kullanıcı bulundu', ($auth->userFromToken($token)['id'] ?? 0) == $id);
    $auth->logout($token);
    $check('Çıkıştan sonra token geçersiz', $auth->userFromToken($token) === null);

    echo "BAŞARISIZ GİRİŞ\n";
    $check('Yanlış parola -> 401', $throws(fn() => $auth->login($email, 'Yanlis999'), 401));
    $throws(fn() => $auth->login($email, 'Yanlis999'), 401);
    $throws(fn() => $auth->login($email, 'Yanlis999'), 401);
    $check('3 hatadan sonra hesap kilitlendi -> 429', $throws(fn() => $auth->login($email, 'Parola123'), 429));

    echo "ROL\n";
    $student = ['id' => $id, 'role' => 'ogrenci'];
    $check('Öğrenci yönetici sayılmaz', !Auth::isAdmin($student));
    $check('Yönetici rolü tanınıyor', Auth::isAdmin(['id' => 1, 'role' => 'yonetici']));
} finally {
    if ($id) $users->delete($id);    // token'lar CASCADE ile silinir
}
echo $fail ? "\n$fail test BAŞARISIZ\n" : "\nTüm kimlik doğrulama testleri başarılı ✔\n";
exit($fail ? 1 : 0);
