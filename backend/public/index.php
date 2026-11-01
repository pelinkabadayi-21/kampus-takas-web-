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

$listingController = fn() => new ListingController(
    new ListingService(new ListingRepository($db()), new CategoryRepository($db())));
$authController  = fn() => new AuthController($authService(), $config['auth']);
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
$router->get('/api/listings/{id}',     fn(int $id) => $listingController()->show($id));
$router->post('/api/listings',         fn()        => $listingController()->store());
$router->put('/api/listings/{id}',     fn(int $id) => $listingController()->update($id));
$router->delete('/api/listings/{id}',  fn(int $id) => $listingController()->destroy($id));

// --- Yönetim (sadece "yonetici" rolü) ---
$router->get('/api/admin/users',              fn()        => $adminController()->users());
$router->put('/api/admin/users/{id}/role',    fn(int $id) => $adminController()->setRole($id));
$router->put('/api/admin/users/{id}/verify',  fn(int $id) => $adminController()->setVerified($id));

// Hataları tek yerden JSON'a çevir
try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (ValidationException $e) {
    Response::json(['success' => false, 'error' => $e->getMessage(), 'errors' => $e->errors], $e->getCode());
} catch (Throwable $e) {
    Response::error($config['app']['debug'] ? $e->getMessage() : 'Sunucu hatası', 500);
}
