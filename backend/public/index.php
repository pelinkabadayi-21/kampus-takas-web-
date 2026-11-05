<?php
/**
 * API GİRİŞ NOKTASI - Tüm istekler buradan geçer (front controller).
 * Örnek: http://localhost/kampus-takas-web-/backend/public/api/health
 */
declare(strict_types=1);

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) $configFile = __DIR__ . '/../config/config.example.php';
$config = require $configFile;
$config['auth'] = ($config['auth'] ?? []) + [               // eski config.php'lerde auth bölümü yoksa varsayılanlar
    'token_ttl_hours' => 8, 'cookie_name' => 'kt_token', 'cookie_secure' => false,
    'max_failed_logins' => 5, 'lock_minutes' => 15, 'student_email_domains' => [],
];

date_default_timezone_set('Europe/Istanbul');

// Güvenlik başlıkları
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

// CORS: Frontend ve API aynı adreste (localhost) çalıştığı için çerezler sorunsuz gider.
header('Access-Control-Allow-Origin: ' . $config['app']['allowed_origin']);
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require __DIR__ . '/../src/bootstrap.php';

// Veritabanı bağlantısını sadece ihtiyaç olunca aç
$db = function () use ($config): PDO {
    try {
        return Database::connect($config['db']);
    } catch (Throwable $e) {
        Response::error('Veritabanına bağlanılamadı. XAMPP MySQL açık mı, migrate.php çalıştırıldı mı?', 500);
    }
};
$authService = fn() => new AuthService(new UserRepository($db()), new AuthTokenRepository($db()), $config['auth']);

// Her istekte: token varsa kullanıcıyı bul (sadece gerektiğinde)
Auth::setResolver(function () use ($authService, $config) {
    $token = Auth::token($config['auth']['cookie_name']);
    return $token ? $authService()->userFromToken($token) : null;
});

$listingController = function () use ($db) {
    $repo   = new ListingRepository($db());
    $images = new ImageService($repo, __DIR__ . '/uploads/listings');
    return new ListingController(new ListingService($repo, new CategoryRepository($db()), $images), $images);
};
$authController  = fn() => new AuthController($authService(), $config['auth']);
$messageController = fn() => new MessageController(
    new MessageService(new MessageRepository($db()), new ListingRepository($db()), new TradeOfferRepository($db())),
    new MessageRepository($db()));
$offerController = fn() => new OfferController(new TradeOfferService(new TradeOfferRepository($db()), new ListingRepository($db())));
$notificationController = fn() => new NotificationController(new NotificationRepository($db()),
    new MessageRepository($db()), new TradeOfferRepository($db()));

// Bildirim kancası: mesaj/teklif olaylarını dinleyip bildirim yazar (DB'ye sadece olay olunca bağlanır)
$listenersRegistered = false;
$registerListeners = function () use (&$listenersRegistered, $db) {
    if (!$listenersRegistered) { NotificationListeners::register(new NotificationRepository($db())); $listenersRegistered = true; }
};
$adminController = fn() => new AdminController(new UserRepository($db()));

$router = new Router();

// --- Sistem ---
$router->get('/api/health', fn() => (new HealthController($config))->index());

// --- Kimlik doğrulama ---
$router->post('/api/auth/register', fn() => $authController()->register());
$router->post('/api/auth/login',    fn() => $authController()->login());
$router->post('/api/auth/logout',   fn() => $authController()->logout());
$router->get('/api/auth/me',        fn() => $authController()->me());

// --- Kategoriler ---
$router->get('/api/categories', fn() => (new CategoryController(new CategoryService(new CategoryRepository($db()))))->index());

// --- İlanlar (okuma herkese açık, yazma giriş gerektirir) ---
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
    if (!in_array($action, ['accept', 'reject', 'cancel', 'complete', 'withdraw'], true)) Response::error('Geçersiz işlem', 404);
    $registerListeners(); $offerController()->action($id, $action);
});

// --- Bildirimler ---
$router->get('/api/notifications',                     fn()                => $notificationController()->index());
$router->get('/api/notifications/counts',              fn()                => $notificationController()->counts());
$router->put('/api/notifications/read-all',            fn()                => $notificationController()->readAll());
$router->put('/api/notifications/{id}/read',           fn(int $id)         => $notificationController()->read($id));

// --- Yönetim (sadece "yonetici" rolü) ---
$router->get('/api/admin/users',              fn()        => $adminController()->users());
$router->put('/api/admin/users/{id}/role',    fn(int $id) => $adminController()->setRole($id));
$router->put('/api/admin/users/{id}/verify',  fn(int $id) => $adminController()->setVerified($id));
$router->get('/api/admin/stats',              fn()        => $adminController()->stats());   // öğrenci bölümü (H5)

// Hataları tek yerden JSON'a çevir
try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (ValidationException $e) {
    Response::json(['success' => false, 'error' => $e->getMessage(), 'errors' => $e->errors], $e->getCode());
} catch (Throwable $e) {
    Response::error($config['app']['debug'] ? $e->getMessage() : 'Sunucu hatası', 500);
}
