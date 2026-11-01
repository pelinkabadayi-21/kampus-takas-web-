<?php
/**
 * ÖRNEK (SENTETİK) VERİ YÜKLEYİCİ
 * Tüm isimler rastgele birleştirilmiş uydurma isimlerdir; e-postalar "example.com"
 * (RFC 2606 ile test için ayrılmış, gerçek kişiye ait olamayan alan adı) kullanır.
 * Öğrenci numaraları "TEST" ile başlar. => Gerçek kişisel veri YOK (KVKK uyumlu).
 *
 * Kullanım: php backend/database/seeds/seed.php   (veya migrate.php --seed)
 * Tüm test kullanıcılarının parolası: Test1234!
 * Yönetici hesabı: yonetici@ogrenci.example.com / Yonetici1234!
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Sadece komut satırından çalışır.'); }

require_once __DIR__ . '/../../src/bootstrap.php';
if (!isset($pdo)) {
    $root   = dirname(__DIR__, 2);
    $config = file_exists("$root/config/config.php") ? require "$root/config/config.php" : require "$root/config/config.example.php";
    $pdo    = Database::connect($config['db']);
}

mt_srand(2026);   // Sabit tohum: her çalıştırmada AYNI veri üretilir (tekrarlanabilir test)
$pick = fn(array $a) => $a[mt_rand(0, count($a) - 1)];

// Önceki örnek veriyi temizle (tablolar korunur)
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['auth_tokens', 'notifications', 'reviews', 'trade_offers', 'messages', 'favorites', 'listing_images', 'listings', 'categories', 'users'] as $t) {
    $pdo->exec("DELETE FROM $t");
    $pdo->exec("ALTER TABLE $t AUTO_INCREMENT = 1");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$users = new UserRepository($pdo);
$cats  = new CategoryRepository($pdo);
$lists = new ListingRepository($pdo);
$favs  = new FavoriteRepository($pdo);
$msgs  = new MessageRepository($pdo);
$offs  = new TradeOfferRepository($pdo);
$revs  = new ReviewRepository($pdo);
$notes = new NotificationRepository($pdo);

// ---------- Kategoriler ----------
$categoryData = [
    'Ders Kitabı' => 'ders-kitabi', 'Roman & Kitap' => 'roman-kitap', 'Elektronik' => 'elektronik',
    'Kırtasiye' => 'kirtasiye', 'Yurt & Ev Eşyası' => 'yurt-ev', 'Giyim' => 'giyim',
    'Spor & Hobi' => 'spor-hobi', 'Diğer' => 'diger',
];
$catIds = [];
foreach ($categoryData as $name => $slug) $catIds[$slug] = $cats->create($name, $slug);

// ---------- Kullanıcılar (uydurma) ----------
$first = ['Deniz', 'Ece', 'Mert', 'Zeynep', 'Kaan', 'Elif', 'Baran', 'Defne', 'Emir', 'Nehir', 'Arda', 'Ilgın', 'Toprak', 'Asya', 'Çınar'];
$last  = ['Ataş', 'Bulut', 'Çelikkaya', 'Demirtaş', 'Erdem', 'Güneş', 'Işık', 'Kartal', 'Oral', 'Soylu', 'Tuna', 'Yıldırım'];
$depts = ['Bilgisayar Programcılığı', 'Muhasebe', 'İşletme', 'Hemşirelik', 'Elektrik', 'Tarım Makineleri', 'Turizm'];
$tr    = ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'Ç' => 'c', 'Ğ' => 'g', 'İ' => 'i', 'Ö' => 'o', 'Ş' => 's', 'Ü' => 'u'];

// Yönetici hesabı (rol tabanlı erişimi test etmek için)
$users->create([
    'full_name' => 'Kampüs Yönetici', 'email' => 'yonetici@ogrenci.example.com', 'password' => 'Yonetici1234!',
    'student_no' => 'TEST0000', 'department' => 'Öğrenci İşleri', 'is_verified' => 1, 'role' => 'yonetici',
]);

$userIds = [];
foreach ($first as $i => $fn) {
    $ln = $pick($last);
    $email = strtolower(strtr("$fn.$ln", $tr)) . ($i + 1) . '@ogrenci.example.com';
    $userIds[] = $users->create([
        'full_name' => "$fn $ln", 'email' => $email, 'password' => 'Test1234!',
        'student_no' => sprintf('TEST%04d', $i + 1), 'department' => $pick($depts),
        'is_verified' => mt_rand(0, 3) > 0 ? 1 : 0,
    ]);
}

// ---------- İlanlar ----------
$items = [
    'ders-kitabi' => [['Temel Programlama Ders Kitabı', 180], ['Veritabanı Yönetim Sistemleri', 220], ['Genel Muhasebe Notları', 90],
                      ['Matematik I Soru Bankası', 120], ['Web Tasarım Temelleri', 160], ['İstatistik Çalışma Kitabı', 110]],
    'roman-kitap' => [['Klasik Romanlar Seti (5 kitap)', 250], ['Polisiye Roman', 60], ['Kişisel Gelişim Kitabı', 70], ['Bilim Kurgu Romanı', 80]],
    'elektronik'  => [['Bilimsel Hesap Makinesi', 350], ['Kablosuz Mouse', 150], ['USB Bellek 64 GB', 120], ['Masa Lambası LED', 200], ['Bluetooth Kulaklık', 400]],
    'kirtasiye'   => [['Teknik Çizim Seti', 140], ['Spiralli Defter Paketi', 60], ['A4 Dosya Klasörü (10 adet)', 50]],
    'yurt-ev'     => [['Mini Buzdolabı', 1800], ['Su Isıtıcı Kettle', 250], ['Nevresim Takımı Tek Kişilik', 300], ['Katlanır Çalışma Masası', 700], ['Çamaşır Kurutmalık', 200]],
    'giyim'       => [['Kışlık Mont (M beden)', 600], ['Mezuniyet Cübbesi', 300], ['Laboratuvar Önlüğü', 150]],
    'spor-hobi'   => [['Yoga Matı', 180], ['Dambıl Seti 2x5 kg', 400], ['Akustik Gitar', 1500], ['Satranç Takımı', 120]],
    'diger'       => [['Bisiklet Kilidi', 100], ['Bitki Saksısı Seti', 80]],
];
$conds  = ['yeni', 'cok_iyi', 'iyi', 'iyi', 'orta', 'yipranmis'];
$places = ['Merkez Kütüphane önü', 'Yemekhane girişi', 'MYO kantini', 'Erkek yurdu kapısı', 'Kız yurdu kapısı', 'Rektörlük önü', 'Otobüs durağı'];
$descs  = ['Temiz kullanıldı, sorunsuz.', 'Bir dönem kullandım, üzerinde birkaç not var.', 'Kutusuyla birlikte veriyorum.',
           'Mezun olduğum için elden çıkarıyorum.', 'Hafif kullanım izi var, çalışıyor.', 'Takas da olur, yazabilirsiniz.'];

$listingIds = []; $ownerOf = [];
foreach ($items as $slug => $list) {
    foreach ($list as [$title, $base]) {
        $type  = $pick(['satis', 'satis', 'satis', 'takas', 'bagis']);
        $owner = $pick($userIds);
        $price = match ($type) { 'satis' => round($base * mt_rand(70, 110) / 100 / 5) * 5, 'takas' => null, 'bagis' => null };
        $id = $lists->create([
            'user_id' => $owner, 'category_id' => $catIds[$slug], 'title' => $title,
            'description' => $pick($descs), 'item_condition' => $pick($conds), 'listing_type' => $type,
            'price' => $price, 'ai_suggested_price' => $type === 'bagis' ? null : $base,
            'meeting_point' => $pick($places),
        ]);
        $listingIds[] = $id; $ownerOf[$id] = $owner;
    }
}

// ---------- Favoriler (N-N) ----------
foreach ($userIds as $u) {
    foreach (array_rand(array_flip($listingIds), 3) as $l) {
        if ($ownerOf[$l] !== $u) $favs->add($u, $l);
    }
}

// ---------- Mesajlar ----------
$questions = ['Merhaba, ilan hâlâ duruyor mu?', 'Fiyatta pazarlık payı var mı?', 'Yarın öğlen buluşabilir miyiz?', 'Takas olarak kitap kabul eder misiniz?'];
$answers   = ['Merhaba, evet duruyor.', 'Biraz indirim yapabilirim.', 'Olur, 12:30 gibi uygun.', 'Hangi kitap olduğuna bakalım.'];
foreach (array_slice($listingIds, 0, 12) as $l) {
    $buyer = $pick(array_values(array_diff($userIds, [$ownerOf[$l]])));
    $k = mt_rand(0, 3);
    $msgs->create($l, $buyer, $ownerOf[$l], $questions[$k]);
    $msgs->create($l, $ownerOf[$l], $buyer, $answers[$k]);
}

// ---------- Takas teklifleri + değerlendirmeler ----------
$offerCount = 0; $reviewCount = 0;
foreach (array_slice($listingIds, 3, 10) as $l) {
    $offerer = $pick(array_values(array_diff($userIds, [$ownerOf[$l]])));
    $own = array_keys($ownerOf, $offerer);
    $oid = $offs->create([
        'listing_id' => $l, 'offerer_id' => $offerer,
        'offered_listing_id' => $own ? $own[0] : null,
        'offered_amount' => $own ? null : mt_rand(5, 30) * 10,
        'note' => 'Uygunsa takas edelim.',
    ]);
    $offerCount++;
    $notes->create($ownerOf[$l], 'teklif', 'İlanınıza yeni bir takas teklifi geldi', "ilan.html?id=$l");

    if (mt_rand(0, 1)) {                       // yarısı kabul edilmiş + değerlendirilmiş
        $offs->setStatus($oid, 'kabul');
        $lists->setStatus($l, 'tamamlandi');
        $revs->create(['trade_offer_id' => $oid, 'reviewer_id' => $ownerOf[$l], 'reviewed_id' => $offerer,
                       'rating' => mt_rand(4, 5), 'comment' => 'Zamanında geldi, sorunsuz takas.']);
        $revs->create(['trade_offer_id' => $oid, 'reviewer_id' => $offerer, 'reviewed_id' => $ownerOf[$l],
                       'rating' => mt_rand(3, 5), 'comment' => 'Ürün anlatıldığı gibiydi.']);
        $reviewCount += 2;
    }
}

$notes->create($userIds[0], 'sistem', 'Kampüs Takas\'a hoş geldiniz! Öğrenci doğrulamanızı tamamlayın.');

printf("Örnek veri yüklendi: %d kullanıcı, %d kategori, %d ilan, %d takas teklifi, %d değerlendirme.\n",
       count($userIds), count($catIds), count($listingIds), $offerCount, $reviewCount);
echo "Öğrenci girişi : " . $users->findById($userIds[0])['email'] . "  /  Test1234!\n";
echo "Yönetici girişi: yonetici@ogrenci.example.com  /  Yonetici1234!\n";
