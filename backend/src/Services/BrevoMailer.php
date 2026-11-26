<?php
/**
 * HARİCİ API: Brevo işlemsel e-posta (Hafta 9)
 *
 * Neden? Önemli bildirimler (yeni teklif, teklif kabulü) sadece site içinde kalırsa kullanıcı
 * siteye girmeden göremez. Brevo'nun ücretsiz planı günde 300 e-postaya izin verir.
 *
 * Kimlik doğrulama: Her istekte "api-key" başlığı gönderilir. Anahtar ortam değişkeninden
 * (BREVO_API_KEY) okunur; koda ve GitHub'a yazılmaz. İstek HTTPS ile gittiği için başlık şifrelidir.
 *
 * Belgeler: https://developers.brevo.com/reference/sendtransacemail
 */
class BrevoMailer implements Mailer
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    /**
     * @param string $apiKey     GİZLİ anahtar (BREVO_API_KEY)
     * @param string $fromEmail  Gönderen adres; Brevo panelinde doğrulanmış olmalı (MAIL_FROM)
     */
    public function __construct(
        private HttpClient $http,
        private string $apiKey,
        private string $fromEmail,
        private string $fromName = 'Kampüs Takas'
    ) {}

    public function name(): string { return 'brevo'; }

    /** POST /v3/smtp/email — JSON gövde ile tek bir e-posta gönderir. */
    public function send(string $toEmail, string $toName, string $subject, string $html, ?int $userId = null): void
    {
        $body = json_encode([
            'sender'      => ['name' => $this->fromName, 'email' => $this->fromEmail],
            'to'          => [['email' => $toEmail, 'name' => $toName]],
            'subject'     => $subject,
            'htmlContent' => $html,
        ], JSON_UNESCAPED_UNICODE);

        $res = $this->http->request('POST', self::ENDPOINT, [
            'api-key'      => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ], $body, ['action' => 'email', 'user_id' => $userId]);

        // Brevo başarılı gönderimde 201 ve "messageId" döndürür
        if (empty($res['json']['messageId'])) {
            throw new ExternalApiException('brevo: cevapta messageId yok', 'brevo', $res['status'], $res['attempts']);
        }
    }
}
