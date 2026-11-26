<?php
/**
 * GÖRSEL DEPOLAMA SÖZLEŞMESİ (Hafta 9)
 *
 * Fotoğrafın nereye kaydedileceğini ImageService bilmez; sadece bu arayüzü kullanır.
 *  - LocalImageStorage:      sunucunun diskine (XAMPP'te ve yedek olarak)
 *  - CloudinaryImageStorage: Cloudinary görsel depolama API'sine (canlıda kalıcı)
 * Yarın başka bir servis (ör. Amazon S3) eklenecekse sadece yeni bir sınıf yazılır.
 */
interface ImageStorage
{
    /** Depolamanın adı (günlük ve sağlık ekranı için): "yerel" veya "cloudinary" */
    public function name(): string;

    /**
     * Hazırlanmış (yeniden çizilmiş, EXIF'i silinmiş) fotoğraf dosyasını kaydeder.
     * @param string $file  Geçici dosyanın yolu
     * @param string $name  Rastgele üretilmiş ad (32 hex karakter, uzantısız)
     * @param string $ext   Uzantı: jpg, png veya webp
     * @return string       Veritabanına yazılacak adres (yerelde göreli yol, bulutta https://…)
     */
    public function put(string $file, string $name, string $ext, ?int $userId = null): string;

    /** Bu adres bu depolamaya mı ait? (Başkasının dosyasını silmeye çalışmamak için) */
    public function owns(string $url): bool;

    /** Fotoğrafı siler. Sadece owns() true dönen adresler için çağrılır. */
    public function delete(string $url, ?int $userId = null): void;
}
