<?php
/**
 * API GİRİŞ NOKTASI - Tüm istekler buradan geçer (front controller).
 * Örnek: http://localhost/kampus-takas-web-/backend/public/api/health
 */
declare(strict_types=1);

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) $configFile = __DIR__ . '/../config/config.example.php';
$config = require $configFile;

date_default_timezone_set('Europe/Istanbul');

// CORS: frontend farklı adresten istek atabilsin
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
$listingController = fn() => new ListingController(
    new ListingService(new ListingRepository($db()), new CategoryRepository($db())));

$router = new Router();

// --- Sistem ---
$router->get('/api/health', fn() => (new HealthController($config))->index());

// --- Kategoriler ---
$router->get('/api/categories', fn() => (new CategoryController(new CategoryService(new CategoryRepository($db()))))->index());

// --- İlanlar (CRUD) ---
$router->get('/api/listings',          fn()       => $listingController()->index());
$router->get('/api/listings/{id}',     fn(int $id) => $listingController()->show($id));
$router->post('/api/listings',         fn()       => $listingController()->store());
$router->put('/api/listings/{id}',     fn(int $id) => $listingController()->update($id));
$router->delete('/api/listings/{id}',  fn(int $id) => $listingController()->destroy($id));

// Hataları tek yerden JSON'a çevir
try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (ValidationException $e) {
    Response::json(['success' => false, 'error' => $e->getMessage(), 'errors' => $e->errors], $e->getCode());
} catch (Throwable $e) {
    Response::error($config['app']['debug'] ? $e->getMessage() : 'Sunucu hatası', 500);
}
