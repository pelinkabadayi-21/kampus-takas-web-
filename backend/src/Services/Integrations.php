<?php
/**
 * HARİCİ SERVİSLERİN KURULUMU (Hafta 9)
 *
 * Ayarlara (ortam değişkenlerine) bakıp hangi servislerin açık olduğuna karar verir:
 *  - CLOUDINARY_URL tanımlıysa → fotoğraflar Cloudinary'ye, değilse diske
 *  - BREVO_API_KEY ve MAIL_FROM tanımlıysa → önemli bildirimler e-postayla da gider, değilse gitmez
 * Anahtar eksikse site HATA VERMEZ; o özellik kapalı çalışır. Böylece XAMPP'te anahtar girmeden de
 * proje çalışır.
 */
class Integrations
{
    /** @param Closure $pdo  Veritabanı bağlantısını veren fonksiyon (günlük yazmak için, sadece gerekince açılır) */
    public function __construct(private array $cfg, private Closure $pdo) {}

    /** Ayar dosyasında "integrations" bölümü yoksa ortam değişkenlerinden okunan varsayılanlar */
    public static function defaults(): array
    {
        return [
            'cloudinary_url' => Env::get('CLOUDINARY_URL'),
            'brevo_api_key'  => Env::get('BREVO_API_KEY'),
            'mail_from'      => Env::get('MAIL_FROM'),
            'mail_from_name' => Env::get('MAIL_FROM_NAME', 'Kampüs Takas'),
            'site_url'       => Env::get('SITE_URL'),
            'gemini_api_key' => Env::get('GEMINI_API_KEY'),                       // Hafta 10
            'ai_model'       => Env::get('AI_MODEL', 'gemini-flash-lite-latest'),  // Hafta 10
            'ai_timeout'     => 20,    // saniye: yapay zekâ cevabı (fotoğraflı istek daha uzun sürebilir)
            'timeout'        => 8,     // saniye: cevap için en fazla bekleme
            'retries'        => 2,     // geçici hatada en fazla 2 kez daha dene
        ];
    }

    /** Cloudinary ayarlıysa görsel depolama nesnesi, değilse null (disk kullanılır). */
    public function imageStorage(): ?ImageStorage
    {
        $url = $this->cfg['cloudinary_url'] ?? '';
        if ($url === '') return null;
        // Fotoğraf yüklemek e-postadan uzun sürebilir: zaman aşımı en az 10 sn
        $storage = CloudinaryImageStorage::fromUrl($url, $this->client('cloudinary', max(10, (int) $this->cfg['timeout'])));
        if (!$storage) error_log('CLOUDINARY_URL biçimi hatalı (beklenen: cloudinary://API_KEY:API_SECRET@CLOUD_NAME)');
        return $storage;
    }

    /** Brevo ayarlıysa e-posta gönderici, değilse null (e-posta gönderilmez). */
    public function mailer(): ?Mailer
    {
        $key = $this->cfg['brevo_api_key'] ?? ''; $from = $this->cfg['mail_from'] ?? '';
        if ($key === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) return null;
        return new BrevoMailer($this->client('brevo', (int) $this->cfg['timeout']), $key, $from, $this->cfg['mail_from_name'] ?? 'Kampüs Takas');
    }

    /** Hafta 10: Gemini anahtarı varsa yapay zekâ sağlayıcısı, yoksa null (öneriler kural tabanlı yapılır). */
    public function llm(): ?LlmProvider
    {
        $key = $this->cfg['gemini_api_key'] ?? '';
        if ($key === '') return null;
        // Yapay zekâda tek yeniden deneme yeterli: kullanıcı formda bekliyor
        return new GeminiProvider($this->client('gemini', (int) ($this->cfg['ai_timeout'] ?? 20), 1), $key,
            ($this->cfg['ai_model'] ?? '') ?: 'gemini-flash-lite-latest');
    }

    /** Sağlık kontrolü ve yönetim paneli için: hangi servis açık? (Anahtarların kendisi ASLA gösterilmez.) */
    public function status(): array
    {
        $cloud = ($this->cfg['cloudinary_url'] ?? '') !== '';
        $mail  = ($this->cfg['brevo_api_key'] ?? '') !== '' && filter_var($this->cfg['mail_from'] ?? '', FILTER_VALIDATE_EMAIL);
        return [
            'image_storage' => $cloud ? 'cloudinary' : 'yerel',
            'email'         => $mail ? 'brevo' : 'kapalı',
            'ai'            => ($this->cfg['gemini_api_key'] ?? '') !== '' ? 'gemini' : 'kural',   // Hafta 10
            'site_url'      => ($this->cfg['site_url'] ?? '') !== '',
        ];
    }

    /** Yönetim paneli için anahtar parmak izleri (anahtarın kendisi değil): uzunluk ve son 4 karakter. */
    public function keyFingerprints(): array
    {
        return [
            'CLOUDINARY_URL' => Env::fingerprint((string) ($this->cfg['cloudinary_url'] ?? '')),
            'BREVO_API_KEY'  => Env::fingerprint((string) ($this->cfg['brevo_api_key'] ?? '')),
            'GEMINI_API_KEY' => Env::fingerprint((string) ($this->cfg['gemini_api_key'] ?? '')),
        ];
    }

    public function siteUrl(): string { return (string) ($this->cfg['site_url'] ?? ''); }

    /** Günlüğe yazan HTTP istemcisi oluşturur. */
    private function client(string $service, int $timeout, ?int $retries = null): HttpClient
    {
        $pdo = $this->pdo;
        return new HttpClient($service, $timeout, 3, $retries ?? (int) ($this->cfg['retries'] ?? 2), null,
            fn(array $entry) => (new ApiLogRepository($pdo()))->log($entry));
    }
}
