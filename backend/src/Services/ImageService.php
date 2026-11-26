<?php
/**
 * İŞ KATMANI - İlan fotoğrafı yükleme.
 *
 * Güvenlik ve gizlilik kuralları:
 *  - Sadece JPEG / PNG / WEBP; tür, dosya adına göre DEĞİL içeriğe göre (finfo) belirlenir
 *  - En fazla 5 MB, en fazla 5 fotoğraf / ilan
 *  - Dosya adı rastgele üretilir (kullanıcının verdiği ad kullanılmaz -> ../ veya .php hilesi olmaz)
 *  - Fotoğraf GD ile YENİDEN ÇİZİLİR: içine gizlenmiş kod temizlenir ve EXIF bilgisi
 *    (telefonun kaydettiği GPS konumu dahil) silinir -> KVKK açısından önemli
 *  - Büyük fotoğraflar en fazla 1600 px'e küçültülür (sayfa hızlı açılsın)
 *
 * Hafta 9 – Harici görsel depolama:
 *  - Fotoğraf hazırlandıktan sonra (EXIF silindikten SONRA) depolamaya gönderilir. Böylece
 *    harici servise konum bilgisi içeren ham fotoğraf hiç gitmez.
 *  - Cloudinary ayarlıysa fotoğraf oraya yüklenir; ayarlı değilse diske kaydedilir.
 *  - Cloudinary'ye ulaşılamazsa (zaman aşımı, 5xx, hız sınırı) fotoğraf KAYBOLMAZ: yedek olarak
 *    diske kaydedilir ve olay günlüğe yazılır. Kullanıcı hata görmez.
 */
class ImageService
{
    public const MAX_IMAGES = 5;
    public const MAX_BYTES  = 5 * 1024 * 1024;
    private const MAX_SIDE  = 1600;
    private const ALLOWED   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private LocalImageStorage $local;

    /** Son yüklemede hangi depolamanın kullanıldığı (yanıtta gösterilir): "cloudinary", "yerel" veya "yerel (yedek)" */
    public string $lastStorage = 'yerel';

    /**
     * İlan deposu, yerel yükleme klasörü, adres öneki ve (varsa) harici depolama alınır.
     * $remote null ise sadece disk kullanılır (XAMPP'te anahtar girilmemişse).
     */
    public function __construct(
        private ListingRepository $listings,
        string $uploadDir,                                  // .../backend/public/uploads/listings
        string $urlPrefix = 'uploads/listings/',
        private ?ImageStorage $remote = null
    ) {
        $this->local = new LocalImageStorage($uploadDir, $urlPrefix);
    }

    /** Fotoğraflar şu an nereye kaydediliyor? (sağlık ekranı için) */
    public function storageName(): string
    {
        return $this->remote?->name() ?? $this->local->name();
    }

    /**
     * $file: $_FILES['image'] dizisi (name, type, tmp_name, error, size)
     * @return array eklenen fotoğraf kaydı
     */
    public function upload(int $listingId, array $file, ListingService $listingService, ?array $actor): array
    {
        $listing = $listingService->get($listingId);
        // Fotoğrafı sadece ilan sahibi (veya yönetici) ekleyebilir
        $listingService->assertCanModify($listing, $actor);

        // Bir ilana en fazla 5 fotoğraf
        if (count($listing['images']) >= self::MAX_IMAGES) {
            throw new ValidationException('Bir ilana en fazla ' . self::MAX_IMAGES . ' fotoğraf eklenebilir');
        }

        // Yükleme sırasında hata oldu mu, dosya çok mu büyük
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) throw new ValidationException('Fotoğraf çok büyük (en fazla 5 MB)');
        if ($err === UPLOAD_ERR_NO_FILE) throw new ValidationException('Fotoğraf seçilmedi');
        if ($err !== UPLOAD_ERR_OK) throw new ValidationException('Fotoğraf yüklenemedi (hata kodu ' . $err . ')');
        if (($file['size'] ?? 0) > self::MAX_BYTES) throw new ValidationException('Fotoğraf çok büyük (en fazla 5 MB)');

        $tmp  = $file['tmp_name'];
        // Dosya türünü uzantıya değil içeriğe bakarak bul (sahte .jpg engellenir)
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset(self::ALLOWED[$mime])) throw new ValidationException('Sadece JPG, PNG veya WEBP fotoğraf yüklenebilir');

        // Gerçekten açılabilen bir resim mi
        $info = @getimagesize($tmp);
        if (!$info || $info[0] < 50 || $info[1] < 50) throw new ValidationException('Geçerli bir fotoğraf değil');
        if ($info[0] * $info[1] > 40_000_000) throw new ValidationException('Fotoğraf çözünürlüğü çok yüksek');

        // Tahmin edilemeyen rastgele dosya adı üret
        $name = bin2hex(random_bytes(16));
        $userId = isset($actor['id']) ? (int) $actor['id'] : null;
        // GD varsa resmi geçici bir dosyaya yeniden çiz: EXIF/GPS konum bilgisi silinir
        // GD yoksa (nadir) dosya olduğu gibi saklanır; tür yine içerikten belirlendi
        $prepared = $tmp; $ext = self::ALLOWED[$mime];
        try {
            if (extension_loaded('gd')) {
                $prepared = tempnam(sys_get_temp_dir(), 'kt_img_');
                $ext = 'jpg';
                $this->reencode($tmp, $mime, $prepared);
            }
            // Hazır fotoğrafı depolamaya gönder (Cloudinary veya disk)
            $url = $this->store($prepared, $name, $ext, $userId);
        } finally {
            // Geçici dosyayı temizle
            if ($prepared !== $tmp && is_file($prepared)) unlink($prepared);
        }

        // Fotoğrafın adresini veritabanına sıradaki numarayla kaydet
        $id = $this->listings->addImage($listingId, $url, $this->listings->nextImageOrder($listingId));
        return $this->listings->findImage($id);
    }

    /**
     * Fotoğrafı depolamaya kaydeder. Harici servis hata verirse diske yedekler (zarif geri çekilme).
     * Harici servisteki hata HttpClient tarafından günlüğe zaten yazıldı.
     */
    private function store(string $file, string $name, string $ext, ?int $userId): string
    {
        if ($this->remote) {
            try {
                $url = $this->remote->put($file, $name, $ext, $userId);
                $this->lastStorage = $this->remote->name();
                return $url;
            } catch (ExternalApiException $e) {
                // Servise ulaşılamadı: kullanıcının fotoğrafı kaybolmasın, diske kaydet
                error_log('Görsel servisi kullanılamadı, diske kaydediliyor: ' . $e->getMessage());
                $this->lastStorage = 'yerel (yedek)';
                return $this->local->put($file, $name, $ext, $userId);
            }
        }
        $this->lastStorage = $this->local->name();
        return $this->local->put($file, $name, $ext, $userId);
    }

    /** Fotoğrafı siler: önce yetki kontrolü, sonra veritabanı kaydı ve diskteki dosya. */
    public function delete(int $listingId, int $imageId, ListingService $listingService, ?array $actor): void
    {
        $listingService->assertCanModify($listingService->get($listingId), $actor);
        $img = $this->listings->findImage($imageId);
        if (!$img || (int) $img['listing_id'] !== $listingId) throw new ValidationException('Fotoğraf bulunamadı', [], 404);
        // Önce veritabanından, sonra diskten sil
        $this->listings->deleteImage($imageId);
        $this->removeFile($img['image_url'], isset($actor['id']) ? (int) $actor['id'] : null);
    }

    /** Seçilen fotoğrafı kapak (ilk sıra) yapar. */
    public function makeCover(int $listingId, int $imageId, ListingService $listingService, ?array $actor): void
    {
        $listingService->assertCanModify($listingService->get($listingId), $actor);
        $img = $this->listings->findImage($imageId);
        if (!$img || (int) $img['listing_id'] !== $listingId) throw new ValidationException('Fotoğraf bulunamadı', [], 404);
        // Kapak fotoğrafını değiştir (eski kapak kaldırılır, yenisi atanır)
        $this->listings->transaction(fn() => $this->listings->setCoverImage($listingId, $imageId));
    }

    /** İlan silinirken o ilanın bütün fotoğraf dosyalarını diskten siler. */
    public function deleteFilesOfListing(int $listingId): void
    {
        foreach ($this->listings->imagesOf($listingId) as $img) $this->removeFile($img['image_url']);
    }

    /**
     * Tek bir fotoğrafı bulunduğu depolamadan siler. Sadece bize ait adreslere dokunur (güvenlik).
     * Harici serviste silme başarısız olursa işlem durdurulmaz: veritabanı kaydı zaten silindi,
     * buluttaki dosya "artık" kalır ve hata günlükte görünür (yönetim panelinden izlenebilir).
     */
    private function removeFile(string $url, ?int $userId = null): void
    {
        if ($this->local->owns($url)) { $this->local->delete($url, $userId); return; }
        if ($this->remote && $this->remote->owns($url)) {
            try { $this->remote->delete($url, $userId); }
            catch (ExternalApiException $e) { error_log('Buluttaki fotoğraf silinemedi: ' . $e->getMessage()); }
        }
    }

    /** Fotoğrafı GD ile yeniden çizip JPEG olarak kaydeder (EXIF ve gizli içerik temizlenir). */
    private function reencode(string $src, string $mime, string $dest): void
    {
        // Dosya türüne göre resmi belleğe aç
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($src),
            'image/png'  => @imagecreatefrompng($src),
            'image/webp' => @imagecreatefromwebp($src),
        };
        if (!$img) throw new ValidationException('Fotoğraf okunamadı, dosya bozuk olabilir');

        // Telefon fotoğrafları yan görünmesin: EXIF yönünü uygula (sonra EXIF zaten silinecek)
        // Telefonla çekilen fotoğraf yan duruyorsa düzelt
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $o = @exif_read_data($src)['Orientation'] ?? 1;
            $img = match ((int) $o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
        }

        $w = imagesx($img); $h = imagesy($img);
        // Uzun kenar 1600 pikseli geçmesin diye küçültme oranı
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        $nw = (int) round($w * $scale); $nh = (int) round($h * $scale);

        // Yeni boş resim oluştur, eskisini içine kopyala, JPEG olarak kaydet
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));   // şeffaf PNG -> beyaz zemin
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        if (!imagejpeg($out, $dest, 82)) throw new RuntimeException('Fotoğraf kaydedilemedi');
        imagedestroy($img); imagedestroy($out);
    }
}
