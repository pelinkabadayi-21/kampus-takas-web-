<?php
/**
 * E-POSTA GÖNDERME SÖZLEŞMESİ (Hafta 9)
 * EmailListeners hangi servisle e-posta gönderildiğini bilmez; sadece bu arayüzü kullanır.
 * Şu an tek uygulaması BrevoMailer. Testte sahte bir Mailer verilir (gerçek e-posta gitmez).
 */
interface Mailer
{
    /** Servisin adı (günlük ve sağlık ekranı için) */
    public function name(): string;

    /**
     * E-posta gönderir. Başarısız olursa ExternalApiException fırlatır.
     * @param int|null $userId  Günlükte "kime gönderildi" bilgisi (e-posta adresi günlüğe yazılmaz)
     */
    public function send(string $toEmail, string $toName, string $subject, string $html, ?int $userId = null): void;
}
