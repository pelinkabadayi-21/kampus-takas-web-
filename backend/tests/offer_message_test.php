<?php
/**
 * MESAJLAŞMA VE TAKAS TEKLİFİ TESTİ (Hafta 7)
 * Geçici test kullanıcıları oluşturur, sonunda siler (ilan, mesaj, teklif, bildirim CASCADE ile silinir).
 * Kullanım: php backend/tests/offer_message_test.php
 */
if (PHP_SAPI !== 'cli') exit('Sadece komut satırından çalışır.');
require __DIR__ . '/../src/bootstrap.php';
$root   = dirname(__DIR__);
$config = file_exists("$root/config/config.php") ? require "$root/config/config.php" : require "$root/config/config.example.php";
$pdo    = Database::connect($config['db']);
date_default_timezone_set('Europe/Istanbul');

$fail = 0;
$check  = function (string $name, bool $ok) use (&$fail) { echo ($ok ? "  ✔ " : "  ✘ ") . $name . PHP_EOL; if (!$ok) $fail++; };
$throws = function (callable $fn, ?int $code = null) { try { $fn(); return false; } catch (ValidationException $e) { return $code === null || $e->getCode() === $code; } };

Events::reset();
$notes = new NotificationRepository($pdo);
NotificationListeners::register($notes);                     // bildirim kancası
$users = new UserRepository($pdo); $lr = new ListingRepository($pdo); $or = new TradeOfferRepository($pdo); $mr = new MessageRepository($pdo);
$ls = new ListingService($lr, new CategoryRepository($pdo));
$ts = new TradeOfferService($or, $lr);
$ms = new MessageService($mr, $lr, $or);
$cat = (int) (new CategoryRepository($pdo))->all()[0]['id'];
$s = time();
$mk = fn($n) => ['id' => $users->create(['full_name' => $n, 'email' => strtolower($n) . "$s@test.example.com", 'password' => 'x', 'is_verified' => 1]),
                 'role' => 'ogrenci', 'is_verified' => 1, 'full_name' => $n];
$A = $mk('Sahip'); $B = $mk('Alici'); $C = $mk('Ucuncu');

try {
    $hedef = $ls->create(['category_id' => $cat, 'title' => 'Test takas kitabı', 'listing_type' => 'takas'], $A);
    $bKit  = $ls->create(['category_id' => $cat, 'title' => 'Alıcının kitabı', 'listing_type' => 'takas'], $B);
    $cKit  = $ls->create(['category_id' => $cat, 'title' => 'Üçüncünün kitabı', 'listing_type' => 'takas'], $C);

    echo "MESAJLAŞMA\n";
    $m = $ms->send($hedef['id'], $B, null, 'Merhaba, takas olur mu?');
    $check('Mesaj ilan sahibine gitti', (int) $m['receiver_id'] === $A['id']);
    $check('Bildirim kancası: ilan sahibine bildirim düştü', $notes->unreadCount($A['id']) === 1);
    $check('İlan sahibi konuşma başlatamaz (spam engeli)', $throws(fn() => $ms->send($hedef['id'], $A, $C['id'], 'Selam'), 403));
    $ms->send($hedef['id'], $A, $B['id'], 'Olur, ne veriyorsun?');
    $check('Konuşmayı üçüncü kişi okuyamaz', $throws(fn() => $ms->conversation($hedef['id'], $C, $B['id']), 403));
    $ms->conversation($hedef['id'], $A, $B['id']);
    $check('Konuşma açılınca mesajlar okundu oldu', $mr->unreadCount($A['id']) === 0);

    echo "TAKAS TEKLİFİ\n";
    $o1 = $ts->create(['listing_id' => $hedef['id'], 'offered_listing_id' => $bKit['id']], $B);
    $o2 = $ts->create(['listing_id' => $hedef['id'], 'offered_listing_id' => $cKit['id']], $C);
    $check('Aynı ilana ikinci bekleyen teklif engellendi', $throws(fn() => $ts->create(['listing_id' => $hedef['id'], 'offered_amount' => 5], $B)));
    $check('Teklifi sadece ilan sahibi kabul eder', $throws(fn() => $ts->accept($o1, $B), 403));
    $ts->accept($o1, $A);
    $check('Kabul: teklif "kabul", iki ilan "rezerve"', $ts->get($o1)['status'] === 'kabul'
        && $lr->findById($hedef['id'])['status'] === 'rezerve' && $lr->findById($bKit['id'])['status'] === 'rezerve');
    $check('Kabul: diğer bekleyen teklif otomatik "red"', $ts->get($o2)['status'] === 'red');
    $check('Bildirim: teklif verene kabul bildirimi', str_contains($notes->forUser($B['id'])[0]['content'], 'kabul'));
    $ts->complete($o1, $B);
    $check('Tamamla: iki ilan "tamamlandi"', $lr->findById($hedef['id'])['status'] === 'tamamlandi' && $lr->findById($bKit['id'])['status'] === 'tamamlandi');
    $check('Tamamlanan takas değerlendirilebilir', (new ReviewService(new ReviewRepository($pdo), $or))->create($o1, $A['id'], 5, 'Sorunsuz') > 0);
} finally {
    foreach ([$A, $B, $C] as $u) $users->delete($u['id']);
    Events::reset();
}
echo $fail ? "\n$fail test BAŞARISIZ\n" : "\nTüm mesajlaşma ve teklif testleri başarılı ✔\n";
exit($fail ? 1 : 0);
