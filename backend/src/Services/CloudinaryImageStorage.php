<?php
/**
 * HARİCİ API: Cloudinary görsel depolama (Hafta 9)
 *
 * Neden? Render'ın ücretsiz planında disk kalıcı değil; sunucu her yeniden başladığında yüklenen
 * fotoğraflar siliniyordu. Cloudinary fotoğrafları kendi sunucularında kalıcı saklar ve hızlı
 * bir adresten (CDN) sunar. Ücretsiz planı öğrenci projesi için yeterlidir.
 *
 * Kimlik doğrulama: "İmzalı yükleme". API gizli anahtarı (api_secret) isteğe EKLENMEZ.
 * Onun yerine gönderilen parametreler alfabetik sıralanıp sonuna gizli anahtar eklenir ve
 * SHA-1 özeti alınır (signature). Cloudinary aynı hesabı yapıp karşılaştırır. Böylece gizli
 * anahtar ağ üzerinden hiç gitmez; imza sadece o parametreler ve o zaman damgası için geçerlidir
 * (Cloudinary 1 saatten eski imzayı kabul etmez → yakalanan bir istek sonradan tekrar kullanılamaz).
 *
 * Belgeler: https://cloudinary.com/documentation/image_upload_api_reference
 */
class CloudinaryImageStorage implements ImageStorage
{
    public const FOLDER = 'kampus-takas/listings';

    /**
     * @param string $cloudName  Hesap adı (adreste görünür, gizli değil)
     * @param string $apiKey     Herkese açık anahtar (gizli değil ama yine de ortam değişkeninde)
     * @param string $apiSecret  GİZLİ anahtar — sadece imza hesaplamada kullanılır
     */
    public function __construct(
        private HttpClient $http,
        private string $cloudName,
        private string $apiKey,
        private string $apiSecret,
        private ?Closure $now = null           // testte sabit zaman vermek için
    ) {}

    /** CLOUDINARY_URL biçimindeki ayardan nesne oluşturur: cloudinary://API_KEY:API_SECRET@CLOUD_NAME */
    public static function fromUrl(string $url, HttpClient $http): ?self
    {
        if (!preg_match('#^cloudinary://([^:]+):([^@]+)@([a-z0-9_-]+)$#i', trim($url), $m)) return null;
        return new self($http, $m[3], $m[1], $m[2]);
    }

    public function name(): string { return 'cloudinary'; }

    /** Fotoğrafı Cloudinary'ye yükler ve kalıcı https adresini döndürür. */
    public function put(string $file, string $name, string $ext, ?int $userId = null): string
    {
        // İmzalanacak parametreler (dosya ve api_key imzaya girmez).
        // Klasör, public_id'nin içine yazılır: "kampus-takas/listings/<rastgele ad>"
        $params = ['public_id' => self::FOLDER . '/' . $name, 'timestamp' => $this->timestamp()];
        $body = $params + [
            'api_key'   => $this->apiKey,
            'signature' => self::sign($params, $this->apiSecret),
            'file'      => new CURLFile($file, 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext), "$name.$ext"),
        ];
        $res = $this->http->request('POST', $this->endpoint('upload'), [], $body, ['action' => 'upload', 'user_id' => $userId]);

        // Cevapta güvenli (https) adres olmalı; yoksa yükleme başarılı sayılmaz
        $url = $res['json']['secure_url'] ?? '';
        if (!is_string($url) || !str_starts_with($url, 'https://')) {
            throw new ExternalApiException('cloudinary: cevapta fotoğraf adresi yok', 'cloudinary', $res['status'], $res['attempts']);
        }
        return $url;
    }

    /** Adres bizim hesabımızın bizim klasörümüzdeki bir fotoğrafı mı? */
    public function owns(string $url): bool
    {
        return $this->publicIdFromUrl($url) !== null;
    }

    /** Fotoğrafı Cloudinary'den siler (destroy). */
    public function delete(string $url, ?int $userId = null): void
    {
        $publicId = $this->publicIdFromUrl($url);
        if ($publicId === null) return;
        $params = ['public_id' => $publicId, 'timestamp' => $this->timestamp()];
        $body = $params + ['api_key' => $this->apiKey, 'signature' => self::sign($params, $this->apiSecret)];
        $this->http->request('POST', $this->endpoint('destroy'), [], http_build_query($body),
            ['action' => 'destroy', 'user_id' => $userId]);
    }

    /**
     * İMZA: parametreleri ada göre sırala → "a=1&b=2" yap → sonuna gizli anahtarı ekle → SHA-1.
     * Değerler URL kodlaması YAPILMADAN birleştirilir (Cloudinary kuralı).
     */
    public static function sign(array $params, string $secret): string
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $k => $v) {
            if ($v === null || $v === '') continue;          // boş parametreler imzaya girmez
            $pairs[] = "$k=$v";
        }
        return sha1(implode('&', $pairs) . $secret);
    }

    /** https://res.cloudinary.com/<hesap>/image/upload/v123/kampus-takas/listings/<ad>.jpg → "kampus-takas/listings/<ad>" */
    private function publicIdFromUrl(string $url): ?string
    {
        $re = '#^https://res\.cloudinary\.com/' . preg_quote($this->cloudName, '#')
            . '/image/upload/(?:v\d+/)?(' . preg_quote(self::FOLDER, '#') . '/[a-f0-9]{32})\.(jpg|png|webp)$#';
        return preg_match($re, $url, $m) ? $m[1] : null;
    }

    /** API adresi: upload veya destroy */
    private function endpoint(string $action): string
    {
        return "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/$action";
    }

    private function timestamp(): int
    {
        return $this->now ? (int) ($this->now)() : time();
    }
}
