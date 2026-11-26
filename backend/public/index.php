<?php
/**
 * API GİRİŞ NOKTASI - Tüm istekler buradan geçer (tek giriş noktası).
 * Örnek: http://localhost/kampus-takas-web-/backend/public/api/health
 */
declare(strict_types=1);

// Tüm sınıfları otomatik yükleyen dosya
require __DIR__ . '/../src/bootstrap.php';

// Hafta 9: API anahtarları ortam değişkenlerinden okunur. XAMPP'te backend/.env dosyası da kullanılabilir
// (GitHub'a yüklenmez, tarayıcıdan açılamaz). Sunucuda tanımlı değişken her zaman önceliklidir.
Env::load(__DIR__ . '/../.env');

// Ayar dosyasını yükle; config.php yoksa örnek ayarları kullan
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) $configFile = __DIR__ . '/../config/config.example.php';
$config = require $configFile;
$config['auth'] = ($config['auth'] ?? []) + [               // eski config.php'lerde auth bölümü yoksa varsayılanlar
    'token_ttl_hours' => 8, 'cookie_name' => 'kt_token', 'cookie_secure' => false,
    'max_failed_logins' => 5, 'lock_minutes' => 15, 'student_email_domains' => [],
];
// Eski config.php'lerde "integrations" bölümü yoksa değerler ortam değişkenlerinden alınır
$config['integrations'] = ($config['integrations'] ?? []) + Integrations::defaults();

// Tarihler Türkiye saatine göre
date_default_timezone_set('Europe/Istanbul');

// Güvenlik başlıkları
// Güvenlik başlıkları: tarayıcı dosya türünü tahmin etmesin, site iframe içine alınmasın
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

// CORS (farklı adresten erişim izni): Arayüz ve API aynı adreste (localhost) çalıştığı için çerezler sorunsuz gider.
header('Access-Control-Allow-Origin: ' . $config['app']['allowed_origin']);
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
// Tarayıcının ön kontrol (OPTIONS) isteğine boş cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Veritabanı bağlantısını sadece ihtiyaç olunca aç
// Veritabanı bağlantısı sadece gerektiğinde açılır
$db = function () use ($config): PDO {
    try {
        return Database::connect($config['db']);
    } catch (Throwable $e) {
        // Bağlantı kurulamazsa kullanıcıya anlaşılır bir hata ver
        Response::error('Veritabanına bağlanılamadı. XAMPP MySQL açık mı, migrate.php çalıştırıldı mı?', 500);
    }
};
// Harici servisler (Cloudinary, Brevo): anahtar yoksa o özellik kapalı çalışır, site hata vermez
$integrations = new Integrations($config['integrations'], $db);

$authService = fn() => new AuthService(new UserRepository($db()), new AuthTokenRepository($db()), $config['auth']);

// Her istekte: oturum anahtarı varsa kullanıcıyı bul (sadece gerektiğinde)
Auth::setResolver(function () use ($authService, $config) {
    $token = Auth::token($config['auth']['cookie_name']);
    return $token ? $authService()->userFromToken($token) : null;
});

// Denetleyicileri ihtiyaç olunca oluşturan küçük fabrikalar (veri katmanı → iş katmanı → denetleyici)
$listingController = function () use ($db, $integrations) {
    $repo   = new ListingRepository($db());
    // Cloudinary ayarlıysa fotoğraflar oraya, değilse diske (Hafta 9)
    $images = new ImageService($repo, __DIR__ . '/uploads/listings', 'uploads/listings/', $integrations->imageStorage());
    return new ListingController(new ListingService($repo, new CategoryRepository($db()), $images), $images);
};
$authController  = fn() => new AuthController($authService(), $config['auth']);
$messageController = fn() => new MessageController(
    new MessageService(new MessageRepository($db()), new ListingRepository($db()), new TradeOfferRepository($db())),
    new MessageRepository($db()));
$offerController = fn() => new OfferController(new TradeOfferService(new TradeOfferRepository($db()), new ListingRepository($db())), new ReviewRepository($db()));
$reviewController = fn() => new ReviewController(new ReviewService(new ReviewRepository($db()), new TradeOfferRepository($db())));
$notificationController = fn() => new NotificationController(new NotificationRepository($db()),
    new MessageRepository($db()), new TradeOfferRepository($db()));

// Bildirim kancası: mesaj/teklif olaylarını dinleyip bildirim yazar (veritabanına sadece olay olunca bağlanır)
$listenersRegistered = false;
// Bildirim dinleyicilerini sadece bir kez kaydet
$registerListeners = function () use (&$listenersRegistered, $db, $integrations) {
    if ($listenersRegistered) return;
    // Site içi bildirimler (Hafta 7)
    NotificationListeners::register(new NotificationRepository($db()));
    // Hafta 9: e-posta bildirimleri — Brevo ayarlıysa aynı olaylara ikinci bir dinleyici eklenir
    if ($mailer = $integrations->mailer()) {
        (new EmailListeners($mailer, new UserRepository($db()), new ApiLogRepository($db()), $integrations->siteUrl()))->register();
    }
    $listenersRegistered = true;
};
$adminController = fn() => new AdminController(new UserRepository($db()), new ApiLogRepository($db()), $integrations);

// Adres (URL) → fonksiyon eşleştirmeleri
$router = new Router();

// --- Sistem ---
// Sağlık kontrolü
$router->get('/api/health', fn() => (new HealthController($config, $integrations))->index());

// --- Kimlik doğrulama ---
// Kayıt, giriş, çıkış
$router->post('/api/auth/register', fn() => $authController()->register());
$router->post('/api/auth/login',    fn() => $authController()->login());
$router->post('/api/auth/logout',   fn() => $authController()->logout());
$router->get('/api/auth/me',        fn() => $authController()->me());
$router->put('/api/me/preferences', fn() => $authController()->preferences());   // Hafta 9: e-posta bildirimi aç/kapat

// --- Kategoriler ---
$router->get('/api/categories', fn() => (new CategoryController(new CategoryService(new CategoryRepository($db()))))->index());

// --- İlanlar (okuma herkese açık, yazma giriş gerektirir) ---
// İlan listesi, detay, ekleme, güncelleme, silme
$router->get('/api/listings',          fn()        => $listingController()->index());
$router->get('/api/listings/options',  fn()        => $listingController()->options());
$router->get('/api/me/listings',       fn()        => $listingController()->mine());
$router->get('/api/listings/{id}',     fn(int $id) => $listingController()->show($id));
$router->post('/api/listings',         fn()        => $listingController()->store());
$router->put('/api/listings/{id}',     fn(int $id) => $listingController()->update($id));
$router->delete('/api/listings/{id}',  fn(int $id) => $listingController()->destroy($id));

// --- İlan fotoğrafları (ilan sahibi veya yönetici) ---
$router->post('/api/listings/{id}/images',                    fn(int $id)           => $listingController()->uploadImage($id));
$router->delete('/api/listings/{id}/images/{imageId}',        fn(int $id, int $img) => $listingController()->deleteImage($id, $img));
$router->put('/api/listings/{id}/images/{imageId}/cover',     fn(int $id, int $img) => $listingController()->coverImage($id, $img));

// --- Mesajlaşma ---
$router->get('/api/messages',                          fn()                => $messageController()->inbox());
$router->get('/api/messages/{listingId}/{userId}',     fn(int $l, int $u)  => $messageController()->conversation($l, $u));
$router->post('/api/messages',                         function () use ($registerListeners, $messageController) { $registerListeners(); $messageController()->send(); });

// --- Takas teklifleri ---
$router->get('/api/offers',                            fn()                => $offerController()->mine());
$router->get('/api/listings/{id}/offers',              fn(int $id)         => $offerController()->forListing($id));
$router->post('/api/offers',                           function () use ($registerListeners, $offerController) { $registerListeners(); $offerController()->store(); });
$router->put('/api/offers/{id}/{action}',              function (int $id, string $action) use ($registerListeners, $offerController) {
    // Sadece bilinen işlemlere izin ver
    if (!in_array($action, ['accept', 'reject', 'cancel', 'complete', 'withdraw'], true)) Response::error('Geçersiz işlem', 404);
    $registerListeners(); $offerController()->action($id, $action);
});

// --- Değerlendirmeler ---
$router->post('/api/reviews',                          function () use ($registerListeners, $reviewController) { $registerListeners(); $reviewController()->store(); });
$router->get('/api/users/{id}/reviews',                fn(int $id)         => $reviewController()->forUser($id));

// --- Bildirimler ---
$router->get('/api/notifications',                     fn()                => $notificationController()->index());
$router->get('/api/notifications/counts',              fn()                => $notificationController()->counts());
$router->put('/api/notifications/read-all',            fn()                => $notificationController()->readAll());
$router->put('/api/notifications/{id}/read',           fn(int $id)         => $notificationController()->read($id));

// --- Yönetim (sadece "yonetici" rolü) ---
// Yönetici işlemleri
$router->get('/api/admin/users',              fn()        => $adminController()->users());
$router->put('/api/admin/users/{id}/role',    fn(int $id) => $adminController()->setRole($id));
$router->put('/api/admin/users/{id}/verify',  fn(int $id) => $adminController()->setVerified($id));
$router->get('/api/admin/stats',              fn()        => $adminController()->stats());   // öğrenci bölümü (H5)
$router->get('/api/admin/integrations',       fn()        => $adminController()->integrations());     // Hafta 9
$router->post('/api/admin/integrations/test', fn()        => $adminController()->testIntegration());  // Hafta 9

// Hataları tek yerden JSON'a çevir
try {
    // Gelen isteği uygun fonksiyona yönlendir
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
// Kural hatası: alan hatalarıyla birlikte 400/403/404 döndür
} catch (ValidationException $e) {
    Response::json(['success' => false, 'error' => $e->getMessage(), 'errors' => $e->errors], $e->getCode());
} catch (Throwable $e) {
    // Beklenmeyen hata: ayrıntıyı gizleyip 500 döndür
    Response::error($config['app']['debug'] ? $e->getMessage() : 'Sunucu hatası', 500);
}
