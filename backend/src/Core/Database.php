<?php
/**
 * VERİ KATMANI - Veritabanı bağlantısı (PDO, tekil nesne / singleton).
 * Repository sınıfları bağlantıyı buradan alır.
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function connect(array $cfg): PDO
    {
        if (self::$pdo === null) {
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$cfg['charset']}";
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,   // SQL injection'a karşı gerçek prepared statement
            ]);
        }
        return self::$pdo;
    }
}
