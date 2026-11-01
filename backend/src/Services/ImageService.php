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
 */
class ImageService
{
    public const MAX_IMAGES = 5;
    public const MAX_BYTES  = 5 * 1024 * 1024;
    private const MAX_SIDE  = 1600;
    private const ALLOWED   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(
        private ListingRepository $listings,
        private string $uploadDir,          // .../backend/public/uploads/listings
        private string $urlPrefix = 'uploads/listings/'
    ) {}

    /**
     * $file: $_FILES['image'] dizisi (name, type, tmp_name, error, size)
     * @return array eklenen fotoğraf kaydı
     */
    public function upload(int $listingId, array $file, ListingService $listingService, ?array $actor): array
    {
        $listing = $listingService->get($listingId);
        $listingService->assertCanModify($listing, $actor);

        if (count($listing['images']) >= self::MAX_IMAGES) {
            throw new ValidationException('Bir ilana en fazla ' . self::MAX_IMAGES . ' fotoğraf eklenebilir');
        }

        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) throw new ValidationException('Fotoğraf çok büyük (en fazla 5 MB)');
        if ($err === UPLOAD_ERR_NO_FILE) throw new ValidationException('Fotoğraf seçilmedi');
        if ($err !== UPLOAD_ERR_OK) throw new ValidationException('Fotoğraf yüklenemedi (hata kodu ' . $err . ')');
        if (($file['size'] ?? 0) > self::MAX_BYTES) throw new ValidationException('Fotoğraf çok büyük (en fazla 5 MB)');

        $tmp  = $file['tmp_name'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset(self::ALLOWED[$mime])) throw new ValidationException('Sadece JPG, PNG veya WEBP fotoğraf yüklenebilir');

        $info = @getimagesize($tmp);
        if (!$info || $info[0] < 50 || $info[1] < 50) throw new ValidationException('Geçerli bir fotoğraf değil');
        if ($info[0] * $info[1] > 40_000_000) throw new ValidationException('Fotoğraf çözünürlüğü çok yüksek');

        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0755, true)) {
            throw new RuntimeException('Yükleme klasörü oluşturulamadı: ' . $this->uploadDir);
        }

        $name = bin2hex(random_bytes(16));
        if (extension_loaded('gd')) {
            $name .= '.jpg';
            $this->reencode($tmp, $mime, $this->uploadDir . '/' . $name);
        } else {
            // GD yoksa (nadir) dosya olduğu gibi saklanır; tür yine içerikten belirlendi
            $name .= '.' . self::ALLOWED[$mime];
            $ok = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $this->uploadDir . '/' . $name) : copy($tmp, $this->uploadDir . '/' . $name);
            if (!$ok) throw new RuntimeException('Fotoğraf kaydedilemedi');
        }

        $id = $this->listings->addImage($listingId, $this->urlPrefix . $name, $this->listings->nextImageOrder($listingId));
        return $this->listings->findImage($id);
    }

    public function delete(int $listingId, int $imageId, ListingService $listingService, ?array $actor): void
    {
        $listingService->assertCanModify($listingService->get($listingId), $actor);
        $img = $this->listings->findImage($imageId);
        if (!$img || (int) $img['listing_id'] !== $listingId) throw new ValidationException('Fotoğraf bulunamadı', [], 404);
        $this->listings->deleteImage($imageId);
        $this->removeFile($img['image_url']);
    }

    /** Seçilen fotoğrafı kapak (ilk sıra) yapar. */
    public function makeCover(int $listingId, int $imageId, ListingService $listingService, ?array $actor): void
    {
        $listingService->assertCanModify($listingService->get($listingId), $actor);
        $img = $this->listings->findImage($imageId);
        if (!$img || (int) $img['listing_id'] !== $listingId) throw new ValidationException('Fotoğraf bulunamadı', [], 404);
        $this->listings->transaction(fn() => $this->listings->setCoverImage($listingId, $imageId));
    }

    public function deleteFilesOfListing(int $listingId): void
    {
        foreach ($this->listings->imagesOf($listingId) as $img) $this->removeFile($img['image_url']);
    }

    private function removeFile(string $url): void
    {
        // Sadece kendi klasörümüzdeki, bizim ürettiğimiz biçimdeki dosyaları sil
        if (!str_starts_with($url, $this->urlPrefix)) return;
        $base = basename($url);
        if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $base) && is_file($this->uploadDir . '/' . $base)) {
            unlink($this->uploadDir . '/' . $base);
        }
    }

    /** Fotoğrafı GD ile yeniden çizip JPEG olarak kaydeder (EXIF ve gizli içerik temizlenir). */
    private function reencode(string $src, string $mime, string $dest): void
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($src),
            'image/png'  => @imagecreatefrompng($src),
            'image/webp' => @imagecreatefromwebp($src),
        };
        if (!$img) throw new ValidationException('Fotoğraf okunamadı, dosya bozuk olabilir');

        // Telefon fotoğrafları yan görünmesin: EXIF yönünü uygula (sonra EXIF zaten silinecek)
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $o = @exif_read_data($src)['Orientation'] ?? 1;
            $img = match ((int) $o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
        }

        $w = imagesx($img); $h = imagesy($img);
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        $nw = (int) round($w * $scale); $nh = (int) round($h * $scale);

        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));   // şeffaf PNG -> beyaz zemin
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        if (!imagejpeg($out, $dest, 82)) throw new RuntimeException('Fotoğraf kaydedilemedi');
        imagedestroy($img); imagedestroy($out);
    }
}
