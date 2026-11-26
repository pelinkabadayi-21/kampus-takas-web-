<?php
/**
 * HARİCİ API ENTEGRASYONU TESTİ (Hafta 9)
 *
 * Gerçek Cloudinary / Brevo'ya istek GİTMEZ: HttpClient'a sahte bir "transport" verilir ve servisin
 * vereceği cevaplar (200, 401, 429, 503, zaman aşımı) taklit edilir. Böylece:
 *  - Anahtar olmadan da test çalışır, kota harcanmaz, kimseye e-posta gitmez.
 *  - Hata durumları (servis çöktü, hız sınırı) isteğe bağlı olarak denenebilir.
 * Veritabanı sunucusu gerekmez: kullanıcı ve günlük tabloları bellek içi SQLite'ta kurulur.
 * Gerçek cURL zaman aşımı, bilgisayarda geçici açılan yavaş bir PHP sunucusuyla denenir.
 *
 * Kullanım: php backend/tests/api_integration_test.php
 * Gerçek servislerle deneme: php backend/tests/api_canli_test.php (anahtar gerekir)
 */
if (PHP_SAPI !== 'cli') exit('Sadece komut satırından çalışır.');
require __DIR__ . '/../src/bootstrap.php';
date_default_timezone_set('Europe/Istanbul');

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail) { echo ($ok ? "  ✔ " : "  ✘ ") . $name . PHP_EOL; if (!$ok) $fail++; };
$apiError = function (callable $fn): ?ExternalApiException { try { $fn(); return null; } catch (ExternalApiException $e) { return $e; } };

// ---------------------------------------------------------------- Bellek içi veritabanı (SQLite)
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, full_name TEXT, email TEXT, password_hash TEXT,
    student_no TEXT, department TEXT, is_verified INT DEFAULT 0, role TEXT DEFAULT 'ogrenci', avatar_url TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP, last_login_at TEXT, email_notifications INT NOT NULL DEFAULT 1)");
$pdo->exec("CREATE TABLE external_api_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, service TEXT, action TEXT, user_id INT,
    status_code INT, success INT, attempts INT, duration_ms INT, error TEXT, created_at TEXT)");
$users = new UserRepository($pdo);
$logs  = new ApiLogRepository($pdo);
$alici = $users->create(['full_name' => 'Ayşe Test', 'email' => 'ayse@test.example.com', 'password' => 'x']);
$satici = $users->create(['full_name' => 'Mehmet Test', 'email' => 'mehmet@test.example.com', 'password' => 'x']);
$kapali = $users->create(['full_name' => 'Kapalı Test', 'email' => 'kapali@test.example.com', 'password' => 'x']);
$users->update($kapali, ['email_notifications' => 0]);

/** Sırayla verilen cevapları döndüren sahte transport; gelen istekleri $calls'a kaydeder. */
function fakeTransport(array $responses, ?array &$calls): callable
{
    $calls = [];
    return function ($method, $url, $headers, $body, $timeout, $ct) use (&$responses, &$calls) {
        $calls[] = compact('method', 'url', 'headers', 'body', 'timeout');
        return array_shift($responses) ?? ['status' => 500, 'headers' => [], 'body' => ''];
    };
}
$waits = [];
$sleep = function (float $s) use (&$waits) { $waits[] = $s; };      // gerçekten beklemesin, süreyi kaydetsin
$logged = [];
$memLog = function (array $e) use (&$logged) { $logged[] = $e; };

// ======================================================================== 1) HTTP İSTEMCİSİ
echo "HTTP İSTEMCİSİ (zaman aşımı, yeniden deneme, hız sınırı)\n";

$c = new HttpClient('deneme', 5, 3, 2, fakeTransport([['status' => 200, 'headers' => [], 'body' => '{"ok":true}']], $calls), $memLog, $sleep);
$r = $c->request('GET', 'https://ornek.test/x', [], null, ['action' => 'test', 'user_id' => 7]);
$check('200 cevabı JSON olarak çözüldü, 1 denemede bitti', $r['json'] === ['ok' => true] && $r['attempts'] === 1);
$check('Başarılı çağrı günlüğe yazıldı (servis, işlem, kullanıcı)', end($logged)['success'] === 1 && end($logged)['action'] === 'test' && end($logged)['user_id'] === 7);

$waits = [];
$c = new HttpClient('deneme', 5, 3, 2, fakeTransport([['status' => 503, 'headers' => [], 'body' => ''], ['status' => 200, 'headers' => [], 'body' => '{}']], $calls), $memLog, $sleep);
$r = $c->request('GET', 'https://ornek.test/x');
$check('503 (geçici hata) → 0,5 sn bekleyip tekrar denedi, 2. denemede başarılı', $r['attempts'] === 2 && $waits === [0.5]);

$waits = [];
$c = new HttpClient('deneme', 5, 3, 2, fakeTransport([['status' => 429, 'headers' => ['retry-after' => '2'], 'body' => ''], ['status' => 200, 'headers' => [], 'body' => '{}']], $calls), $memLog, $sleep);
$c->request('POST', 'https://ornek.test/x');
$check('429 (hız sınırı) → servisin istediği Retry-After: 2 sn kadar beklendi', $waits === [2.0]);

$waits = [];
$c = new HttpClient('deneme', 5, 3, 2, fakeTransport([['status' => 429, 'headers' => ['retry-after' => '60'], 'body' => ''], ['status' => 200, 'headers' => [], 'body' => '{}']], $calls), $memLog, $sleep);
$c->request('POST', 'https://ornek.test/x');
$check('Retry-After: 60 → en fazla 3 sn beklendi (kullanıcı asılı kalmasın)', $waits === [HttpClient::MAX_RETRY_WAIT]);

$c = new HttpClient('deneme', 5, 3, 2, fakeTransport([['status' => 401, 'headers' => [], 'body' => '{"message":"Key not found"}']], $calls), $memLog, $sleep);
$e = $apiError(fn() => $c->request('POST', 'https://ornek.test/x'));
$check('401 (yanlış anahtar) tekrar DENENMEDİ: 1 istek', $e && count($calls) === 1 && $e->status === 401);
$check('Hata mesajı anlaşılır: "API anahtarı geçersiz" + servisin mesajı', $e && str_contains($e->getMessage(), 'API anahtarı geçersiz') && str_contains($e->getMessage(), 'Key not found'));

$waits = [];
$to = ['status' => 0, 'headers' => [], 'body' => '', 'error' => 'zaman aşımı (5 sn)'];
$c = new HttpClient('deneme', 5, 3, 2, fakeTransport([$to, $to, $to], $calls), $memLog, $sleep);
$e = $apiError(fn() => $c->request('POST', 'https://ornek.test/x', [], null, ['action' => 'upload']));
$check('Zaman aşımı 3 kez → 3 deneme (0,5 + 1 sn bekleme) sonra ExternalApiException', $e && $e->attempts === 3 && $e->isTimeout() && $waits === [0.5, 1.0]);
$check('Başarısız çağrı günlüğe yazıldı: durum kodu yok, hata "zaman aşımı"', end($logged)['success'] === 0 && end($logged)['status_code'] === null && str_contains(end($logged)['error'], 'zaman aşımı'));

$c = new HttpClient('deneme', 5, 3, 2, fakeTransport([['status' => 200, 'headers' => [], 'body' => '{}']], $calls), function () { throw new RuntimeException('günlük tablosu yok'); }, $sleep);
$check('Günlük yazılamasa bile asıl istek başarılı döner', $c->request('GET', 'https://ornek.test/x')['status'] === 200);

// Gerçek cURL zaman aşımı: 3 sn geç cevap veren geçici bir yerel sunucu açılır, istemci 1 sn bekler
if (function_exists('proc_open') && function_exists('curl_init')) {
    $dir = sys_get_temp_dir() . '/kt_slow_' . bin2hex(random_bytes(3));
    mkdir($dir);
    file_put_contents("$dir/index.php", '<?php sleep(3); echo "{}";');
    $port = random_int(20000, 40000);
    $proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $dir], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    usleep(400_000);
    $c = new HttpClient('yavas', 1, 1, 0);
    $t0 = microtime(true);
    $e = $apiError(fn() => $c->request('GET', "http://127.0.0.1:$port/"));
    $sure = microtime(true) - $t0;
    proc_terminate($proc); proc_close($proc);
    unlink("$dir/index.php"); rmdir($dir);
    $check(sprintf('Gerçek cURL: 3 sn geç cevap veren sunucu 1 sn sonra kesildi (%.1f sn)', $sure), $e && $e->isTimeout() && $sure < 2.5);
} else {
    echo "  – gerçek cURL zaman aşımı testi atlandı (proc_open/curl yok)\n";
}

// ======================================================================== 2) CLOUDINARY
echo "CLOUDINARY (görsel depolama)\n";

// Cloudinary belgelerindeki örnek: bu parametreler + "abcd" gizli anahtarı → bilinen imza
$check('İmza Cloudinary belgelerindeki örnekle aynı (sıralama + SHA-1)', CloudinaryImageStorage::sign(
    ['timestamp' => 1315060510, 'public_id' => 'sample_image', 'eager' => 'w_400,h_300,c_pad|w_260,h_200,c_crop'], 'abcd')
    === 'bfd09f95f331f558cbd1320e67aa8d488770583e');

$check('CLOUDINARY_URL çözüldü; hatalı biçim reddedildi',
    CloudinaryImageStorage::fromUrl('cloudinary://123:gizli@kampus', new HttpClient('cloudinary')) !== null
    && CloudinaryImageStorage::fromUrl('https://yanlis', new HttpClient('cloudinary')) === null);

$name = str_repeat('ab', 16);
$imgFile = tempnam(sys_get_temp_dir(), 'kt_');
file_put_contents($imgFile, 'resim');
$secure = "https://res.cloudinary.com/kampus/image/upload/v17/kampus-takas/listings/$name.jpg";
$http = new HttpClient('cloudinary', 10, 3, 2, fakeTransport([['status' => 200, 'headers' => [], 'body' => json_encode(['secure_url' => $secure])]], $calls), $memLog, $sleep);
$cl = new CloudinaryImageStorage($http, 'kampus', '123456', 'COK_GIZLI', fn() => 1700000000);
$url = $cl->put($imgFile, $name, 'jpg', $alici);
$sent = $calls[0]['body'];
$check('Yükleme doğru adrese gitti: /v1_1/kampus/image/upload', $calls[0]['url'] === 'https://api.cloudinary.com/v1_1/kampus/image/upload');
$check('İstekte imza ve api_key var, gizli anahtar (api_secret) YOK', isset($sent['signature'], $sent['api_key'])
    && !str_contains(json_encode(array_diff_key($sent, ['file' => 1])), 'COK_GIZLI'));
$check('İmza istekteki parametrelerden doğru hesaplandı', $sent['signature'] === CloudinaryImageStorage::sign(['public_id' => $sent['public_id'], 'timestamp' => 1700000000], 'COK_GIZLI'));
$check('Dönen https adresi kaydedilecek adres oldu', $url === $secure);
$check('owns(): kendi klasörümüzdeki adres bize ait', $cl->owns($secure));
$check('owns(): başka hesabın / başka klasörün adresi bize ait DEĞİL (silinmez)',
    !$cl->owns(str_replace('/kampus/', '/baskasi/', $secure)) && !$cl->owns(str_replace('kampus-takas/listings', 'profil', $secure)));
$http = new HttpClient('cloudinary', 10, 3, 2, fakeTransport([['status' => 200, 'headers' => [], 'body' => '{"result":"ok"}']], $calls), $memLog, $sleep);
(new CloudinaryImageStorage($http, 'kampus', '123456', 'COK_GIZLI'))->delete($secure);
parse_str($calls[0]['body'], $del);
$check('Silme: /image/destroy adresine doğru public_id gönderildi', str_ends_with($calls[0]['url'], '/image/destroy') && $del['public_id'] === "kampus-takas/listings/$name");

// Cloudinary çökük → ImageService fotoğrafı diske yedekler
$tmpDir = sys_get_temp_dir() . '/kt_up_' . bin2hex(random_bytes(3));
$down = new HttpClient('cloudinary', 10, 3, 1, fakeTransport([['status' => 503, 'headers' => [], 'body' => ''], ['status' => 503, 'headers' => [], 'body' => '']], $calls), $memLog, $sleep);
$svc = new ImageService(new ListingRepository($pdo), $tmpDir, 'uploads/listings/', new CloudinaryImageStorage($down, 'kampus', '1', 's'));
$store = (new ReflectionMethod(ImageService::class, 'store'));
$saved = $store->invoke($svc, $imgFile, $name, 'jpg', $alici);
$check('Cloudinary 503 verdi → fotoğraf KAYBOLMADI, diske yedeklendi', $saved === "uploads/listings/$name.jpg" && is_file("$tmpDir/$name.jpg") && $svc->lastStorage === 'yerel (yedek)');
$check('Hata yönetim panelinde görünsün diye günlükte', end($logged)['service'] === 'cloudinary' && end($logged)['success'] === 0 && end($logged)['attempts'] === 2);
@unlink("$tmpDir/$name.jpg"); @rmdir($tmpDir); @unlink($imgFile);

// ======================================================================== 3) BREVO + E-POSTA KANCASI
echo "BREVO (e-posta) VE E-POSTA BİLDİRİMİ\n";

$http = new HttpClient('brevo', 5, 3, 2, fakeTransport([['status' => 201, 'headers' => [], 'body' => '{"messageId":"<1@brevo>"}']], $calls), fn($e) => $logs->log($e), $sleep);
(new BrevoMailer($http, 'xkeysib-GIZLI', 'bildirim@kampustakas.example.com'))->send('ayse@test.example.com', 'Ayşe', 'Konu', '<p>Merhaba</p>', $alici);
$req = json_decode($calls[0]['body'], true);
$check('Brevo: anahtar "api-key" başlığında, istek /v3/smtp/email adresine', $calls[0]['headers']['api-key'] === 'xkeysib-GIZLI' && str_ends_with($calls[0]['url'], '/v3/smtp/email'));
$check('Brevo: JSON gövdede alıcı, gönderen, konu doğru', $req['to'][0]['email'] === 'ayse@test.example.com' && $req['sender']['email'] === 'bildirim@kampustakas.example.com' && $req['subject'] === 'Konu');
$row = $logs->recent(1)[0];
$check('Günlükte anahtar, e-posta adresi ve içerik YOK; sadece sonuç var', !str_contains(json_encode($row), 'GIZLI') && !str_contains(json_encode($row), 'ayse@') && $row['action'] === 'email' && (int) $row['success'] === 1);
$pdo->exec('DELETE FROM external_api_logs');

/** Gönderilen e-postaları hafızada tutan sahte Mailer; istenirse hata fırlatır */
class FakeMailer implements Mailer
{
    public array $sent = [];
    public bool $broken = false;
    public function __construct(private ApiLogRepository $logs) {}
    public function name(): string { return 'brevo'; }
    public function send(string $to, string $name, string $subject, string $html, ?int $userId = null): void
    {
        if ($this->broken) {
            $this->logs->log(['service' => 'brevo', 'action' => 'email', 'user_id' => $userId, 'success' => 0, 'attempts' => 3, 'error' => 'zaman aşımı (5 sn)']);
            throw new ExternalApiException('brevo: zaman aşımı (5 sn)', 'brevo', null, 3);
        }
        $this->logs->log(['service' => 'brevo', 'action' => 'email', 'user_id' => $userId, 'success' => 1, 'status_code' => 201]);
        $this->sent[] = compact('to', 'subject', 'html');
    }
}
Events::reset();
$mailer = new FakeMailer($logs);
$siteNotes = [];
Events::on('offer.accepted', function ($e) use (&$siteNotes) { $siteNotes[] = 'site içi bildirim'; });   // Hafta 7 dinleyicisinin yerine
(new EmailListeners($mailer, $users, $logs, 'https://kampus.example.com'))->register();

Events::emit('offer.accepted', ['offer_id' => 1, 'offerer_id' => $alici, 'listing_id' => 3, 'listing_title' => 'Fizik 1 kitabı',
    'meeting_point' => 'Kütüphane önü', 'auto_closed' => []]);
$check('E1: offer.accepted → teklif verene e-posta gitti', count($mailer->sent) === 1 && $mailer->sent[0]['to'] === 'ayse@test.example.com');
$check('E-postada buluşma noktası ve sitedeki sayfanın bağlantısı var', str_contains($mailer->sent[0]['html'], 'Kütüphane önü')
    && str_contains($mailer->sent[0]['html'], 'https://kampus.example.com/frontend/pages/teklifler.html?sekme=giden'));

Events::emit('offer.created', ['owner_id' => $satici, 'listing_title' => '<script>alert(1)</script>', 'offerer_name' => 'Ayşe']);
$check('E4: Kullanıcının yazdığı başlık e-postada kod olarak çalışmaz (HTML kaçışı)', str_contains(end($mailer->sent)['html'], '&lt;script&gt;') && !str_contains(end($mailer->sent)['html'], '<script>'));

$once = count($mailer->sent);
Events::emit('offer.withdrawn', ['other_id' => $kapali, 'listing_title' => 'Hesap makinesi']);
$check('E2: E-posta bildirimini kapatan kullanıcıya e-posta gitmedi', count($mailer->sent) === $once);

$mailer->broken = true; $siteNotes = [];
$hata = null;
try { Events::emit('offer.accepted', ['offerer_id' => $satici, 'listing_title' => 'Kitap', 'meeting_point' => null, 'auto_closed' => []]); }
catch (Throwable $t) { $hata = $t; }
$check('E3: Brevo zaman aşımına uğradı ama olay HATA FIRLATMADI (teklif kabulü bozulmaz)', $hata === null);
$check('E3: Site içi bildirim yine yazıldı', $siteNotes === ['site içi bildirim']);
$mailer->broken = false;

// ---- ÖĞRENCİ BÖLÜMÜ (Hafta 9) — E5: kullanıcı başına saatte en fazla 5 e-posta
echo "ÖĞRENCİ BÖLÜMÜ — E5: Bir kullanıcıya saatte en fazla " . EmailListeners::HOURLY_LIMIT . " e-posta\n";
$pdo->exec('DELETE FROM external_api_logs'); $mailer->sent = [];
$el = new EmailListeners($mailer, $users, $logs);
$sonuclar = [];
for ($i = 1; $i <= 7; $i++) $sonuclar[] = $el->notify($alici, "Bildirim $i", 'deneme');
$check('İlk 5 e-posta gönderildi, 6. ve 7. engellendi', $sonuclar === [true, true, true, true, true, false, false] && count($mailer->sent) === 5);
$check('Engellenen e-postalar günlükte "E5" hatasıyla, 0 denemeyle görünüyor',
    (int) $pdo->query("SELECT COUNT(*) FROM external_api_logs WHERE error LIKE 'E5:%' AND attempts = 0")->fetchColumn() === 2);
$check('Başka bir kullanıcının sınırı ayrı sayılıyor', $el->notify($satici, 'Merhaba', 'deneme') === true);
$pdo->exec("UPDATE external_api_logs SET created_at = '" . date('Y-m-d H:i:s', time() - 3700) . "'");
$check('1 saat geçince tekrar gönderilebiliyor', $el->notify($alici, 'Yeni saat', 'deneme') === true);
Events::reset();

// ======================================================================== 4) ORTAM DEĞİŞKENLERİ VE AYARLAR
echo "ORTAM DEĞİŞKENLERİ (anahtarlar kodda değil)\n";
$envFile = tempnam(sys_get_temp_dir(), 'kt_env_');
file_put_contents($envFile, "# açıklama satırı\nKT_TEST_A=birinci\nKT_TEST_B=\"tırnaklı değer\"\nkucuk_harf=yok\nKT_TEST_C=dosyadan\n");
putenv('KT_TEST_C=sunucudan');
Env::load($envFile);
$check('.env dosyası okundu (açıklama atlandı, tırnaklar temizlendi)', Env::get('KT_TEST_A') === 'birinci' && Env::get('KT_TEST_B') === 'tırnaklı değer' && Env::get('kucuk_harf') === '');
$check('Sunucuda tanımlı değişken .env dosyasıyla EZİLMEDİ', Env::get('KT_TEST_C') === 'sunucudan');
unlink($envFile); putenv('KT_TEST_A'); putenv('KT_TEST_B'); putenv('KT_TEST_C');

$getPdo = fn() => $pdo;
$off = new Integrations(['cloudinary_url' => '', 'brevo_api_key' => '', 'mail_from' => '', 'timeout' => 8, 'retries' => 2], $getPdo);
$check('Anahtar yoksa servisler kapalı, site hata vermez (fotoğraflar diske)', $off->imageStorage() === null && $off->mailer() === null
    && $off->status() === ['image_storage' => 'yerel', 'email' => 'kapalı', 'site_url' => false]);
$on = new Integrations(['cloudinary_url' => 'cloudinary://1:2@kampus', 'brevo_api_key' => 'xkeysib-1', 'mail_from' => 'a@b.example.com', 'timeout' => 8, 'retries' => 2], $getPdo);
$check('Anahtar varsa Cloudinary ve Brevo açılıyor', $on->imageStorage() instanceof CloudinaryImageStorage && $on->mailer() instanceof BrevoMailer);
$check('Durum bilgisinde anahtarların kendisi YOK', !str_contains(json_encode($on->status()), 'xkeysib') && !str_contains(json_encode($on->status()), 'cloudinary://'));

$kaynak = '';
foreach (glob(dirname(__DIR__) . '/{src,config,public}/**/*.php', GLOB_BRACE) as $f) $kaynak .= file_get_contents($f);
foreach (glob(dirname(__DIR__) . '/config/*.php') as $f) $kaynak .= file_get_contents($f);
$check('Kaynak kodda gerçek anahtar yok (xkeysib-…, cloudinary://…@)', !preg_match('/xkeysib-[a-z0-9]{20,}|cloudinary:\/\/\d+:[^@\s]+@/i', $kaynak));

echo $fail ? "\n$fail test BAŞARISIZ\n" : "\nTüm harici API entegrasyonu testleri başarılı ✔\n";
exit($fail ? 1 : 0);
