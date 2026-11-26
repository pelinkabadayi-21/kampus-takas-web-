<?php
/**
 * GERÇEK SERVİSLERLE DENEME (Hafta 9 – öğrenci bölümü: "API çağrılarını kendin yazıp test et")
 *
 * api_integration_test.php sahte cevaplarla çalışır. Bu betik ise GERÇEK Cloudinary ve Brevo'ya
 * istek atar. Anahtarlar backend/.env dosyasından (veya ortam değişkenlerinden) okunur; anahtarı
 * olmayan servis atlanır.
 *
 *   1) Cloudinary: 64x64 bir test görseli üretir → yükler → dönen adresi açıp kontrol eder → siler
 *   2) Brevo: MAIL_TEST_TO adresine bir test e-postası gönderir
 *   3) Gemini: örnek bir ilan için kategori ve fiyat ister, cevabın JSON olduğunu kontrol eder (Hafta 10)
 *   4) Yanlış anahtarla Brevo'ya istek atar → 401 bekler, tekrar DENENMEMESİ gerekir
 *
 * Kullanım: php backend/tests/api_canli_test.php
 */
if (PHP_SAPI !== 'cli') exit('Sadece komut satırından çalışır.');
require __DIR__ . '/../src/bootstrap.php';
Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set('Europe/Istanbul');

$fail = 0; $ran = 0;
$check = function (string $name, bool $ok) use (&$fail, &$ran) { $ran++; echo ($ok ? "  ✔ " : "  ✘ ") . $name . PHP_EOL; if (!$ok) $fail++; };
// Her çağrının sonucunu ekrana yaz (anahtar yazılmaz)
$log = function (array $e) {
    printf("    ↳ %s/%s: %s, %d deneme, %d ms%s\n", $e['service'], $e['action'],
        $e['success'] ? 'başarılı' : 'BAŞARISIZ', $e['attempts'], $e['duration_ms'], $e['error'] ? ' – ' . $e['error'] : '');
};

// ---------------------------------------------------------------- 1) Cloudinary
echo "CLOUDINARY\n";
$cloudUrl = Env::get('CLOUDINARY_URL');
if ($cloudUrl === '') {
    echo "  – atlandı: CLOUDINARY_URL tanımlı değil (backend/.env)\n";
} else {
    $storage = CloudinaryImageStorage::fromUrl($cloudUrl, new HttpClient('cloudinary', 15, 3, 2, null, $log));
    $check('CLOUDINARY_URL biçimi doğru', $storage !== null);
    if ($storage) {
        $tmp = tempnam(sys_get_temp_dir(), 'kt_live_');
        $img = imagecreatetruecolor(64, 64);
        imagefill($img, 0, 0, imagecolorallocate($img, 91, 63, 209));
        imagejpeg($img, $tmp, 80);
        try {
            $url = $storage->put($tmp, bin2hex(random_bytes(16)), 'jpg');
            echo "    adres: $url\n";
            $check('Yükleme başarılı, https adresi döndü', str_starts_with($url, 'https://res.cloudinary.com/'));
            // Adres gerçekten açılıyor mu?
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
            curl_exec($ch);
            $check('Yüklenen görsel adresten açılıyor (HTTP 200)', curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200);
            curl_close($ch);
            $storage->delete($url);
            $check('Görsel silindi', true);
        } catch (ExternalApiException $e) {
            $check('Cloudinary çağrısı: ' . $e->getMessage(), false);
        } finally {
            unlink($tmp);
        }
    }
}

// ---------------------------------------------------------------- 2) Brevo
echo "BREVO\n";
$key = Env::get('BREVO_API_KEY'); $from = Env::get('MAIL_FROM'); $to = Env::get('MAIL_TEST_TO');
if ($key === '' || $from === '' || $to === '') {
    echo "  – atlandı: BREVO_API_KEY, MAIL_FROM ve MAIL_TEST_TO tanımlı olmalı (backend/.env)\n";
} else {
    $mailer = new BrevoMailer(new HttpClient('brevo', 8, 3, 2, null, $log), $key, $from);
    try {
        $mailer->send($to, 'Test', 'Kampüs Takas – canlı API testi', '<p>Bu e-posta <b>api_canli_test.php</b> tarafından gönderildi ✔</p>');
        $check("Test e-postası gönderildi → $to (gelen kutusunu / spam klasörünü kontrol edin)", true);
    } catch (ExternalApiException $e) {
        $check('Brevo çağrısı: ' . $e->getMessage(), false);
    }
}

// ---------------------------------------------------------------- 3) Gemini (Hafta 10)
echo "GEMINI (yapay zekâ)\n";
$gKey = Env::get('GEMINI_API_KEY');
if ($gKey === '') {
    echo "  – atlandı: GEMINI_API_KEY tanımlı değil (backend/.env)\n";
} else {
    $model = Env::get('AI_MODEL', 'gemini-flash-lite-latest');
    $gem = new GeminiProvider(new HttpClient('gemini', 30, 3, 1, null, $log), $gKey, $model);
    $slugs = ['ders-kitabi', 'roman-kitap', 'elektronik', 'kirtasiye', 'yurt-ev', 'giyim', 'spor-hobi', 'diger'];
    $system = 'Sen bir ikinci el ilan asistanısın. Kategoriyi listeden seç, Türk lirası olarak makul ikinci el fiyat öner. Sadece JSON döndür.';
    $user = "KATEGORİLER: " . implode(', ', $slugs) . "\nİLAN (veri):\nBaşlık: Casio fx-991 bilimsel hesap makinesi\nDurum: İyi\nTür: Satılık";
    try {
        $raw = $gem->generateJson($system, $user, AiSuggestionService::schema($slugs));
        echo "    model: $model\n    ham cevap: " . mb_substr(preg_replace('/\s+/', ' ', $raw), 0, 200) . "\n";
        $j = AiSuggestionService::parse($raw);
        $check('Gemini JSON döndürdü', is_array($j));
        $check('Kategori listeden: ' . ($j['category'] ?? '?'), in_array($j['category'] ?? '', $slugs, true));
        $check('Fiyat sayı: ' . ($j['price'] ?? '?') . ' ₺', is_numeric($j['price'] ?? null) && $j['price'] > 0);
    } catch (ExternalApiException $e) {
        $check('Gemini çağrısı: ' . $e->getMessage() . ($e->status === 404 ? ' (model adı yanlış olabilir: AI_MODEL)' : ''), false);
    }
}

// ---------------------------------------------------------------- 4) Yanlış anahtar
echo "YANLIŞ ANAHTAR (hata yönetimi)\n";
$attempts = 0;
$wrong = new BrevoMailer(new HttpClient('brevo', 8, 3, 2, null, function ($e) use ($log, &$attempts) { $attempts = $e['attempts']; $log($e); }),
    'yanlis-anahtar', 'test@example.com');
try {
    $wrong->send('test@example.com', 'Test', 'olmamalı', 'x');
    $check('Yanlış anahtar reddedilmeliydi', false);
} catch (ExternalApiException $e) {
    if ($e->status === null) {
        echo "  – atlandı: Brevo'ya ulaşılamadı (internet bağlantısı yok?): {$e->getMessage()}\n";
    } else {
        $check("Yanlış anahtar → HTTP {$e->status} (401 beklenir)", $e->status === 401);
        $check('Kalıcı hata olduğu için tekrar denenmedi (1 deneme)', $attempts === 1);
    }
}

echo $fail ? "\n$fail test BAŞARISIZ\n" : ($ran ? "\nCanlı API testleri başarılı ✔\n" : "\nHiçbir test çalışmadı (anahtar/internet yok)\n");
exit($fail ? 1 : 0);
