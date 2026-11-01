<?php
/**
 * CRUD TESTİ - Veri erişim katmanının gerçek MySQL üzerinde çalıştığını gösterir.
 * Kendi test kullanıcılarını oluşturur, sonunda siler (ON DELETE CASCADE ile ilişkili her şey de silinir).
 *
 * Kullanım: php backend/tests/crud_test.php
 */
if (PHP_SAPI !== 'cli') exit('Sadece komut satırından çalışır.');
require __DIR__ . '/../src/bootstrap.php';
$root   = dirname(__DIR__);
$config = file_exists("$root/config/config.php") ? require "$root/config/config.php" : require "$root/config/config.example.php";
$pdo    = Database::connect($config['db']);

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail) {
    echo ($ok ? "  ✔ " : "  ✘ ") . $name . PHP_EOL;
    if (!$ok) $fail++;
};

$users = new UserRepository($pdo);
$lists = new ListingRepository($pdo);
$cats  = new CategoryRepository($pdo);
$notes = new NotificationRepository($pdo);
$offs  = new TradeOfferRepository($pdo);
$svc   = new ListingService($lists, $cats);
$trade = new TradeOfferService($offs, $lists, $notes);

$stamp = time();
$a = $users->create(['full_name' => 'Test Satıcı', 'email' => "satici$stamp@test.example.com", 'password' => 'x']);
$b = $users->create(['full_name' => 'Test Alıcı',  'email' => "alici$stamp@test.example.com",  'password' => 'x']);
$catId = (int) $cats->all()[0]['id'];

try {
    echo "CREATE\n";
    $l = $svc->create(['user_id' => $a, 'category_id' => $catId, 'title' => 'CRUD Test Kitabı', 'listing_type' => 'satis', 'price' => 100]);
    $check('İlan eklendi (id ' . $l['id'] . ')', $l['title'] === 'CRUD Test Kitabı');

    echo "READ\n";
    $check('ID ile okundu, kategori adı JOIN ile geldi', !empty($lists->findById($l['id'])['category_name']));
    $check('Arama ile bulundu', $svc->search(['q' => 'CRUD Test'])['total'] >= 1);

    echo "UPDATE\n";
    $check('Fiyat güncellendi', (float) $svc->update($l['id'], ['price' => 80])['price'] === 80.0);

    echo "KISITLAR\n";
    try { $pdo->exec("INSERT INTO listings (user_id, category_id, title, listing_type, price) VALUES ($a, $catId, 'x', 'satis', -1)"); $check('CHECK negatif fiyatı engelledi', false); }
    catch (PDOException $e) { $check('CHECK negatif fiyatı engelledi', true); }
    try { $pdo->exec("INSERT INTO listings (user_id, category_id, title, listing_type, price) VALUES (999999, $catId, 'x', 'satis', 1)"); $check('FK olmayan kullanıcıyı engelledi', false); }
    catch (PDOException $e) { $check('FK olmayan kullanıcıyı engelledi', true); }

    echo "TRANSACTION\n";
    $oid = $trade->create(['listing_id' => $l['id'], 'offerer_id' => $b, 'offered_amount' => 70]);
    $trade->accept($oid, $a);
    $check('Teklif kabul edildi', $offs->findById($oid)['status'] === 'kabul');
    $check('İlan "rezerve" oldu (aynı işlemde)', $lists->findById($l['id'])['status'] === 'rezerve');

    echo "DELETE\n";
    $svc->delete($l['id']);
    $check('İlan silindi', $lists->findById($l['id']) === null);
    $check('Teklif de silindi (CASCADE)', $offs->findById($oid) === null);
} finally {
    $users->delete($a);
    $users->delete($b);
}

echo $fail ? "\n$fail test BAŞARISIZ\n" : "\nTüm CRUD testleri başarılı ✔\n";
exit($fail ? 1 : 0);
