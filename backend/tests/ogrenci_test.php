<?php
/**
 * ÖĞRENCİ BÖLÜMLERİ TESTİ — Hafta 4–7'de öğrencinin eklediği kısıt, uç ve kurallar.
 * Kullanım: php backend/tests/ogrenci_test.php
 */
if (PHP_SAPI !== 'cli') exit('Sadece komut satırından çalışır.');
require __DIR__ . '/../src/bootstrap.php';
$root   = dirname(__DIR__);
$config = file_exists("$root/config/config.php") ? require "$root/config/config.php" : require "$root/config/config.example.php";
$pdo    = Database::connect($config['db']);
date_default_timezone_set('Europe/Istanbul');

$fail = 0;
$check  = function (string $name, bool $ok) use (&$fail) { echo ($ok ? "  ✔ " : "  ✘ ") . $name . PHP_EOL; if (!$ok) $fail++; };
$throws = function (callable $fn) { try { $fn(); return false; } catch (ValidationException $e) { return true; } };

$users = new UserRepository($pdo); $lr = new ListingRepository($pdo);
$ls = new ListingService($lr, new CategoryRepository($pdo));
$ts = new TradeOfferService(new TradeOfferRepository($pdo), $lr);
$cat = (int) (new CategoryRepository($pdo))->all()[0]['id'];
$s = time();
$A = ['id' => $users->create(['full_name' => 'Test Satici', 'email' => "s$s@test.example.com", 'password' => 'x', 'is_verified' => 1]), 'role' => 'ogrenci', 'is_verified' => 1];
$B = ['id' => $users->create(['full_name' => 'Test Alici',  'email' => "a$s@test.example.com", 'password' => 'x', 'is_verified' => 1]), 'role' => 'ogrenci', 'is_verified' => 1];

try {
    echo "HAFTA 4 — Elle yazılan CHECK kısıtı (başlık 3-120 karakter)\n";
    try { $pdo->exec("INSERT INTO listings (user_id, category_id, title, listing_type) VALUES ({$A['id']}, $cat, 'ab', 'bagis')"); $ok = false; }
    catch (PDOException $e) { $ok = true; }
    $check('Veritabanı 2 karakterlik başlığı reddetti (chk_listing_title_len)', $ok);

    echo "HAFTA 5 — Rol kontrollü uç (GET /api/admin/stats)\n";
    $st = $users->stats();
    $check('İstatistikler hesaplandı: ' . json_encode($st), $st['users'] >= 2);
    $check('Öğrenci rolü yönetici sayılmıyor (uç 403 döner)', !Auth::isAdmin($A));

    echo "HAFTA 6 — Başlık tamamen büyük harf olamaz\n";
    $check('"ACİL SATILIK KİTAP" reddedildi', $throws(fn() => $ls->create(['category_id' => $cat, 'title' => 'ACİL SATILIK KİTAP', 'listing_type' => 'bagis'], $A)));
    $check('"USB 64 GB" gibi kısa başlık kabul edildi', (bool) $ls->create(['category_id' => $cat, 'title' => 'USB 64 GB', 'listing_type' => 'bagis'], $A));

    echo "HAFTA 7 — Teklif ilan fiyatının yarısından az olamaz\n";
    $l = $ls->create(['category_id' => $cat, 'title' => 'Test satış kitabı', 'listing_type' => 'satis', 'price' => 200], $A);
    $check('200 ₺ ilana 90 ₺ teklif reddedildi', $throws(fn() => $ts->create(['listing_id' => $l['id'], 'offered_amount' => 90], $B)));
    $check('200 ₺ ilana 100 ₺ teklif kabul edildi', $ts->create(['listing_id' => $l['id'], 'offered_amount' => 100], $B) > 0);
} finally {
    $users->delete($A['id']); $users->delete($B['id']);
}
echo $fail ? "\n$fail test BAŞARISIZ\n" : "\nTüm öğrenci bölümü testleri başarılı ✔\n";
exit($fail ? 1 : 0);
