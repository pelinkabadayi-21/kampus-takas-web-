<?php
/**
 * API GİRİŞ NOKTASI - Tüm istekler buradan geçer (front controller).
 * Örnek: http://localhost/kampus-takas-web-/backend/public/api/health
 */
declare(strict_types=1);

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    $configFile = __DIR__ . '/../config/config.example.php';
}
$config = require $configFile;

date_default_timezone_set('Europe/Istanbul');

// CORS: frontend farklı adresten istek atabilsin
header('Access-Control-Allow-Origin: ' . $config['app']['allowed_origin']);
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Sınıfları otomatik yükle (src/ altındaki klasörler)
spl_autoload_register(function (string $class) {
    foreach (['Core', 'Controllers', 'Services', 'Repositories'] as $dir) {
        $file = __DIR__ . "/../src/$dir/$class.php";
        if (file_exists($file)) { require $file; return; }
    }
});

$router = new Router();

$router->get('/api/health', fn() => (new HealthController($config))->index());

$router->get('/api/categories', function () use ($config) {
    try {
        $db = Database::connect($config['db']);
    } catch (Throwable $e) {
        Response::error('Veritabanına bağlanılamadı. schema.sql içe aktarıldı mı?', 500);
    }
    (new CategoryController(new CategoryService(new CategoryRepository($db))))->index();
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
