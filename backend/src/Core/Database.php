<?php
/**
 * VERİ KATMANI - Veritabanı bağlantısı (PDO, tekil nesne / singleton).
 * Repository sınıfları bağlantıyı buradan alır.
 */
class Database
{
    private static ?PDO $pdo = null;

    /** Veritabanına bir kez bağlanır, sonraki çağrılarda aynı bağlantıyı döndürür. */
    public static function connect(array $cfg): PDO
    {
        // Daha önce bağlanılmadıysa yeni bağlantı aç
        if (self::$pdo === null) {
            // Bağlantı adresi: sunucu, port, veritabanı adı, karakter seti (utf8mb4 = Türkçe karakterler + emoji)
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$cfg['charset']}";
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                // Hata olursa sessiz kalma, istisna (exception) fırlat
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Satırları ['sütun' => değer] biçiminde dizi olarak getir
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,   // SQL injection'a karşı gerçek prepared statement
            ]);
        }
        // Hep aynı bağlantıyı döndür (her sorguda yeniden bağlanmamak için)
        return self::$pdo;
    }
}
