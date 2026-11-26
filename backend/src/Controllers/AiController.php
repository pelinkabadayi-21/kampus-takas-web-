<?php
/** SUNUM KATMANI - Yapay zekâ öneri uç noktaları (Hafta 10) */
class AiController
{
    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
    private const AI_IMAGE_SIDE   = 768;           // yapay zekâya giden fotoğrafın uzun kenarı (maliyet ve hız)

    public function __construct(private AiSuggestionService $service, private ?AiSuggestionRepository $log = null) {}

    // POST /api/ai/suggest   (giriş gerekli)
    // JSON: {title, description, item_condition, listing_type}
    // veya multipart/form-data: aynı alanlar + isteğe bağlı "image" (fotoğraf)
    public function suggest(): void
    {
        $user = Auth::requireUser();
        $isMultipart = str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');
        $input = $isMultipart ? $_POST : Request::json();
        $image = $isMultipart && !empty($_FILES['image']['tmp_name']) ? $this->prepareImage($_FILES['image']) : null;

        Response::json(['success' => true, 'data' => $this->service->suggest($input, $image, (int) $user['id'])]);
    }

    // GET /api/admin/ai-log   (yönetici) — AI kullanım günlüğü: özet ve son öneriler
    public function adminLog(): void
    {
        Auth::requireRole('yonetici');
        Response::json(['success' => true, 'data' => [
            'enabled' => $this->service->enabled(),
            'prompt_version' => AiSuggestionService::PROMPT_VERSION,
            'stats'   => $this->log->stats(7),
            'recent'  => $this->log->recent(20),
        ]]);
    }

    /**
     * Yüklenen fotoğrafı yapay zekâya göndermeye hazırlar (A3):
     *  - tür içerikten kontrol edilir (JPEG/PNG/WEBP), en fazla 5 MB
     *  - GD ile 768 px'e küçültülüp JPEG olarak yeniden çizilir → EXIF/GPS bilgisi silinir, istek küçülür
     * GD yoksa fotoğraf gönderilmez (öneri yine metinle yapılır).
     */
    private function prepareImage(array $f): ?array
    {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
        if ($f['size'] > self::MAX_IMAGE_BYTES) throw new ValidationException('Fotoğraf en fazla 5 MB olabilir', ['image' => 'Çok büyük']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new ValidationException('Sadece JPG, PNG veya WEBP fotoğraf yüklenebilir', ['image' => 'Geçersiz tür']);
        }
        if (!extension_loaded('gd')) return null;

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($f['tmp_name']),
            'image/png'  => @imagecreatefrompng($f['tmp_name']),
            'image/webp' => @imagecreatefromwebp($f['tmp_name']),
        };
        if (!$src) throw new ValidationException('Fotoğraf okunamadı', ['image' => 'Bozuk dosya']);
        $w = imagesx($src); $h = imagesy($src);
        $scale = min(1, self::AI_IMAGE_SIDE / max($w, $h));
        $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));        // şeffaf PNG → beyaz zemin
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start(); imagejpeg($dst, null, 80); $data = ob_get_clean();
        imagedestroy($src); imagedestroy($dst);
        return ['mime' => 'image/jpeg', 'data' => $data];
    }
}
