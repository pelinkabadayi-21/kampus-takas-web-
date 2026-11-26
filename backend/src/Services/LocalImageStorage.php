<?php
/**
 * Fotoğrafları sunucunun diskine kaydeder (backend/public/uploads/listings).
 * XAMPP'te varsayılan budur. Canlıda Cloudinary'ye ulaşılamazsa YEDEK olarak da kullanılır.
 * Not: Render'ın ücretsiz planında disk kalıcı değildir; sunucu yeniden başlayınca bu dosyalar silinir.
 */
class LocalImageStorage implements ImageStorage
{
    /** Fotoğrafların kaydedileceği klasör ve veritabanına yazılacak adres öneki alınır. */
    public function __construct(private string $uploadDir, private string $urlPrefix = 'uploads/listings/') {}

    public function name(): string { return 'yerel'; }

    /** Geçici dosyayı yükleme klasörüne taşır ve göreli adresini döndürür. */
    public function put(string $file, string $name, string $ext, ?int $userId = null): string
    {
        // Yükleme klasörü yoksa oluştur
        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0755, true)) {
            throw new RuntimeException('Yükleme klasörü oluşturulamadı: ' . $this->uploadDir);
        }
        $dest = $this->uploadDir . '/' . $name . '.' . $ext;
        // Tarayıcıdan gelen dosya ise move_uploaded_file, bizim ürettiğimiz geçici dosya ise copy
        $ok = is_uploaded_file($file) ? move_uploaded_file($file, $dest) : copy($file, $dest);
        if (!$ok) throw new RuntimeException('Fotoğraf kaydedilemedi');
        return $this->urlPrefix . $name . '.' . $ext;
    }

    /** Sadece kendi klasörümüzdeki, bizim ürettiğimiz biçimdeki dosyalar bize aittir (güvenlik). */
    public function owns(string $url): bool
    {
        return str_starts_with($url, $this->urlPrefix)
            && (bool) preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', basename($url));
    }

    /** Dosyayı diskten siler (dosya zaten yoksa sessizce geçer). */
    public function delete(string $url, ?int $userId = null): void
    {
        if (!$this->owns($url)) return;
        $path = $this->uploadDir . '/' . basename($url);
        if (is_file($path)) unlink($path);
    }
}
