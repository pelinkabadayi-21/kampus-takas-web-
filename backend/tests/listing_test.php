<?php
/**
 * İLAN MODÜLÜ TESTİ - İş kuralları ve fotoğraf yükleme (Hafta 6).
 * Geçici test kullanıcıları ve geçici bir yükleme klasörü kullanır, sonunda hepsini siler.
 * Kullanım: php backend/tests/listing_test.php
 */
if (PHP_SAPI !== 'cli') exit('Sadece komut satırından çalışır.');
require __DIR__ . '/../src/bootstrap.php';
$root   = dirname(__DIR__);
$config = file_exists("$root/config/config.php") ? require "$root/config/config.php" : require "$root/config/config.example.php";
$pdo    = Database::connect($config['db']);

$fail = 0;
$check  = function (string $name, bool $ok) use (&$fail) { echo ($ok ? "  ✔ " : "  ✘ ") . $name . PHP_EOL; if (!$ok) $fail++; };
$throws = function (callable $fn, ?int $code = null) { try { $fn(); return false; } catch (ValidationException $e) { return $code === null || $e->getCode() === $code; } };

$tmpDir = sys_get_temp_dir() . '/kt_test_' . bin2hex(random_bytes(4));
$users  = new UserRepository($pdo);
$repo   = new ListingRepository($pdo);
$images = new ImageService($repo, $tmpDir);
$svc    = new ListingService($repo, new CategoryRepository($pdo), $images);
$catId  = (int) (new CategoryRepository($pdo))->all()[0]['id'];
$s = time();
$a = $users->create(['full_name' => 'Test Doğrulanmamış', 'email' => "unv$s@test.example.com", 'password' => 'x', 'is_verified' => 0]);
$b = $users->create(['full_name' => 'Test Diğer',         'email' => "oth$s@test.example.com", 'password' => 'x', 'is_verified' => 1]);
$ua = ['id' => $a, 'role' => 'ogrenci', 'is_verified' => 0];
$ub = ['id' => $b, 'role' => 'ogrenci', 'is_verified' => 1];

try {
    echo "İŞ KURALLARI\n";
    $l = $svc->create(['category_id' => $catId, 'title' => 'Test Kitabı 1', 'listing_type' => 'satis', 'price' => 50, 'ai_suggested_price' => 1], $ua);
    $check('İlan oluşturuldu, sahibi oturumdaki kullanıcı', (int) $l['user_id'] === $a);
    $check('Kullanıcı yapay zekâ fiyatını yazamadı', $l['ai_suggested_price'] === null);
    $check('Aynı başlıkta ikinci aktif ilan engellendi', $throws(fn() => $svc->create(['category_id' => $catId, 'title' => 'test kitabı 1', 'listing_type' => 'bagis'], $ua)));
    $svc->create(['category_id' => $catId, 'title' => 'Test Kitabı 2', 'listing_type' => 'bagis'], $ua);
    $svc->create(['category_id' => $catId, 'title' => 'Test Kitabı 3', 'listing_type' => 'bagis'], $ua);
    $check('Doğrulanmamış öğrenci 4. ilanı açamadı', $throws(fn() => $svc->create(['category_id' => $catId, 'title' => 'Test Kitabı 4', 'listing_type' => 'bagis'], $ua)));
    $check('Fiyat üst sınırı (100.000 ₺)', $throws(fn() => $svc->update($l['id'], ['price' => 999999], $ua)));
    $check('Başkasının ilanını düzenleyemez (403)', $throws(fn() => $svc->update($l['id'], ['price' => 1], $ub), 403));
    $check('Sahip "rezerve" seçemez', $throws(fn() => $svc->update($l['id'], ['status' => 'rezerve'], $ua)));
    $check('Bağışa çevirince fiyat silindi', $svc->update($l['id'], ['listing_type' => 'bagis'], $ua)['price'] === null);
    $svc->update($l['id'], ['status' => 'kaldirildi'], $ua);
    $check('Kaldırılan ilanı başkası göremez (404)', $throws(fn() => $svc->getVisible($l['id'], $ub), 404));

    echo "FOTOĞRAF\n";
    $src = tempnam(sys_get_temp_dir(), 'img');
    $im = imagecreatetruecolor(2000, 1000); imagejpeg($im, $src); imagedestroy($im);
    $img = $images->upload($l['id'], ['tmp_name' => $src, 'error' => 0, 'size' => filesize($src), 'name' => 'x.jpg'], $svc, $ua);
    $file = $tmpDir . '/' . basename($img['image_url']);
    $check('Fotoğraf yüklendi ve 1600 px\'e küçültüldü', is_file($file) && getimagesize($file)[0] === 1600);
    $fake = tempnam(sys_get_temp_dir(), 'fake'); file_put_contents($fake, '<?php echo 1; ?>');
    $check('Uzantısı .jpg olan PHP dosyası reddedildi', $throws(fn() => $images->upload($l['id'], ['tmp_name' => $fake, 'error' => 0, 'size' => 20, 'name' => 'a.jpg'], $svc, $ua)));
    $check('Başkası fotoğraf ekleyemez (403)', $throws(fn() => $images->upload($l['id'], ['tmp_name' => $src, 'error' => 0, 'size' => 10, 'name' => 'x.jpg'], $svc, $ub), 403));
    $svc->delete($l['id'], $ua);
    $check('İlan silinince fotoğraf dosyası da silindi', !is_file($file));
    @unlink($src); @unlink($fake);
} finally {
    $users->delete($a); $users->delete($b);          // ilanlar CASCADE ile silinir
    array_map('unlink', glob("$tmpDir/*") ?: []); @rmdir($tmpDir);
}
echo $fail ? "\n$fail test BAŞARISIZ\n" : "\nTüm ilan modülü testleri başarılı ✔\n";
exit($fail ? 1 : 0);
