<?php
/**
 * Harici bir servise (Cloudinary, Brevo) yapılan çağrı başarısız olduğunda fırlatılır.
 * Kullanıcıya gösterilmez; çağıran servis yakalayıp yedek yola geçer veya anlaşılır bir mesaj verir.
 */
class ExternalApiException extends RuntimeException
{
    /**
     * @param string   $service   Hangi servis (ör. "cloudinary", "brevo")
     * @param int|null $status    HTTP durum kodu; ağ hatası veya zaman aşımında null
     * @param int      $attempts  Kaç kez denendi (yeniden denemeler dahil)
     */
    public function __construct(
        string $message,
        public readonly string $service,
        public readonly ?int $status = null,
        public readonly int $attempts = 1
    ) {
        parent::__construct($message);
    }

    /** Zaman aşımı mı oldu? (durum kodu yok ve mesajda "zaman aşımı" geçiyor) */
    public function isTimeout(): bool
    {
        return $this->status === null && str_contains($this->getMessage(), 'zaman aşımı');
    }
}
