<?php
/**
 * MIGRATION ÇALIŞTIRICI
 * migrations/ klasöründeki .sql dosyalarını numara sırasıyla çalıştırır.
 * Hangi dosyanın çalıştığını "migrations" tablosunda tutar; aynı dosya iki kez çalışmaz.
 *
 * Kullanım (XAMPP -> Shell):
 *   php backend/database/migrate.php            -> bekleyen migration'ları çalıştır
 *   php backend/database/migrate.php --fresh    -> tüm tabloları silip baştan kur
 *   php backend/database/migrate.php --seed     -> kurulumdan sonra örnek veriyi yükle
 *   php backend/database/migrate.php --status   -> hangi migration çalıştı, listele
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Sadece komut satırından çalışır.'); }

$root   = dirname(__DIR__);
$config = file_exists("$root/config/config.php") ? require "$root/config/config.php" : require "$root/config/config.example.php";
$db     = $config['db'];
$args   = array_slice($argv, 1);

// 1) Sunucuya bağlan ve veritabanını yoksa oluştur
$pdo = new PDO("mysql:host={$db['host']};port={$db['port']};charset={$db['charset']}", $db['user'], $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci");
$pdo->exec("USE `{$db['name']}`");

// 2) --fresh: tüm tabloları sil
if (in_array('--fresh', $args)) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $pdo->exec("DROP TABLE `$t`");
        echo "  silindi: $t\n";
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// 3) Takip tablosu
$pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(190) NOT NULL UNIQUE,
    ran_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB');

$done  = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(__DIR__ . '/migrations/*.sql');
sort($files);

if (in_array('--status', $args)) {
    foreach ($files as $f) {
        printf("  [%s] %s\n", in_array(basename($f), $done) ? 'x' : ' ', basename($f));
    }
    exit(0);
}

// 4) Bekleyenleri çalıştır
$count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $done)) continue;

    foreach (splitSql(file_get_contents($file)) as $stmt) {
        $pdo->exec($stmt);
    }
    $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)')->execute([$name]);
    echo "  çalıştı: $name\n";
    $count++;
}
echo $count ? "$count migration çalıştırıldı.\n" : "Bekleyen migration yok, veritabanı güncel.\n";

// 5) --seed
if (in_array('--seed', $args)) {
    require __DIR__ . '/seeds/seed.php';
}

/** SQL dosyasını yorumlardan temizleyip ';' ile biten komutlara böler. */
function splitSql(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    return array_values(array_filter(array_map('trim', explode(';', $sql))));
}
