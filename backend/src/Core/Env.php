<?php
/**
 * ORTAM DEĞİŞKENLERİ (Hafta 9)
 *
 * API anahtarları koda veya GitHub'a YAZILMAZ. İki yerden okunur:
 *  1) Sunucunun ortam değişkenleri (Render panelindeki "Environment" bölümü)
 *  2) backend/.env dosyası (XAMPP'te geliştirme için; .gitignore'da olduğu için GitHub'a gitmez)
 * Sunucuda tanımlı değer her zaman önceliklidir; .env sadece eksik olanları doldurur.
 * backend/.htaccess "Require all denied" olduğu için .env dosyası tarayıcıdan açılamaz.
 */
class Env
{
    /** .env dosyasını okur: her satır ANAHTAR=değer biçimindedir, # ile başlayan satırlar açıklamadır. */
    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) return;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            // Boş ve açıklama satırlarını atla
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            // Değer tırnak içindeyse tırnakları at: KEY="değer"
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            // Anahtar adı sadece büyük harf, rakam ve _ olabilir
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) continue;
            // Sunucuda zaten tanımlıysa ona dokunma (sunucu ayarı önceliklidir)
            if (getenv($key) !== false) continue;
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }

    /** Ortam değişkenini okur; tanımlı değilse veya boşsa varsayılanı döndürür. */
    public static function get(string $key, string $default = ''): string
    {
        $v = getenv($key);
        if ($v === false || $v === '') $v = $_ENV[$key] ?? '';
        return $v === '' ? $default : (string) $v;
    }
}
