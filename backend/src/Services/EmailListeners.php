<?php
/**
 * E-POSTA BİLDİRİM KANCALARI (Hafta 9)
 *
 * Hafta 7'de kurulan olay sistemi (Events) sayesinde teklif servisine HİÇ dokunulmadı:
 * sadece aynı olayları dinleyen yeni bir dinleyici eklendi. Site içi bildirim (NotificationListeners)
 * aynen çalışmaya devam ediyor; e-posta onun yanına EK olarak gidiyor.
 *
 * Kurallar:
 *  E1  Sadece önemli olaylarda e-posta gider: yeni teklif, teklif kabulü, takastan vazgeçme.
 *      (Her mesajda e-posta göndermek kullanıcıyı rahatsız eder; mesajlar sitede görünüyor.)
 *  E2  Kullanıcı Hesabım sayfasından e-posta bildirimlerini kapatabilir (users.email_notifications).
 *  E3  E-posta gönderilemezse (zaman aşımı, yanlış anahtar, servis kapalı) asıl işlem BOZULMAZ:
 *      teklif yine kabul edilir, site içi bildirim yine yazılır; hata sadece günlüğe düşer.
 *  E4  E-postada karşı tarafın e-posta adresi veya telefonu yer almaz; sadece adı ve ilan başlığı.
 *      Kullanıcıdan gelen metinler HTML'e kaçış karakterleriyle (htmlspecialchars) yazılır.
 *  E5  ÖĞRENCİ BÖLÜMÜ: Bir kullanıcıya bir saatte en fazla 5 e-posta gönderilir.
 */
class EmailListeners
{
    public const HOURLY_LIMIT = 5;      // E5

    /**
     * @param string $siteUrl  E-postadaki bağlantılar için sitenin adresi (SITE_URL); boşsa bağlantı eklenmez
     */
    public function __construct(
        private Mailer $mailer,
        private UserRepository $users,
        private ApiLogRepository $logs,
        private string $siteUrl = ''
    ) {}

    /** Önemli olayları dinlemeye başlar (E1). */
    public function register(): void
    {
        // Yeni teklif → ilan sahibine
        Events::on('offer.created', fn(array $e) => $this->notify($e['owner_id'],
            'Yeni takas teklifi: ' . $e['listing_title'],
            "<b>{$this->h($e['offerer_name'])}</b>, <b>„{$this->h($e['listing_title'])}”</b> ilanınıza bir teklif verdi.",
            'teklifler.html?sekme=gelen'));

        // Teklif kabul edildi → teklif verene (buluşma noktasıyla birlikte)
        Events::on('offer.accepted', fn(array $e) => $this->notify($e['offerer_id'],
            'Teklifiniz kabul edildi 🎉',
            "<b>„{$this->h($e['listing_title'])}”</b> için teklifiniz kabul edildi.<br>Buluşma noktası: "
                . $this->h($e['meeting_point'] ?: 'mesajlaşarak belirleyin'),
            'teklifler.html?sekme=giden'));

        // Kabulden sonra vazgeçildi → karşı tarafa
        Events::on('offer.withdrawn', fn(array $e) => $this->notify($e['other_id'],
            'Takas iptal edildi',
            "<b>„{$this->h($e['listing_title'])}”</b> takası karşı taraf tarafından iptal edildi. İlan tekrar yayında.",
            'teklifler.html'));
    }

    /**
     * Kullanıcıya e-posta gönderir. Gönderildiyse true, herhangi bir sebeple gönderilmediyse false.
     * Asla istisna fırlatmaz (E3).
     */
    public function notify(int $userId, string $subject, string $htmlMessage, string $page = ''): bool
    {
        try {
            $u = $this->users->findById($userId);
            // Kullanıcı yoksa veya e-posta bildirimini kapattıysa gönderme (E2)
            if (!$u || (int) ($u['email_notifications'] ?? 1) === 0) return false;

            // E5: Son 60 dakikada bu kişiye 5 e-posta gittiyse yenisini gönderme
            if ($this->logs->countRecent('email', $userId, 60) >= self::HOURLY_LIMIT) {
                $this->logs->log(['service' => $this->mailer->name(), 'action' => 'email', 'user_id' => $userId,
                    'success' => 0, 'attempts' => 0, 'error' => 'E5: kullanıcının saatlik e-posta sınırı doldu']);
                return false;
            }

            $this->mailer->send($u['email'], $u['full_name'], $subject, $this->template($u['full_name'], $htmlMessage, $page), $userId);
            return true;
        } catch (Throwable $e) {
            // E3: Hata asıl işlemi bozmaz. Ayrıntı HttpClient tarafından günlüğe zaten yazıldı.
            error_log('E-posta gönderilemedi: ' . $e->getMessage());
            return false;
        }
    }

    /** Basit HTML e-posta şablonu */
    private function template(string $name, string $message, string $page): string
    {
        $link = $this->siteUrl !== '' && $page !== ''
            ? '<p><a href="' . $this->h(rtrim($this->siteUrl, '/') . '/frontend/pages/' . $page)
              . '" style="background:#5b3fd1;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none">Sitede görüntüle</a></p>'
            : '';
        return '<div style="font-family:Arial,sans-serif;max-width:520px;color:#222">'
            . '<h2 style="color:#5b3fd1">📚 Kampüs Takas</h2>'
            . '<p>Merhaba ' . $this->h($name) . ',</p>'
            . "<p>$message</p>$link"
            . '<p style="color:#888;font-size:12px">Bu e-postayı almak istemiyorsanız Hesabım sayfasından e-posta bildirimlerini kapatabilirsiniz.</p>'
            . '</div>';
    }

    /** HTML kaçışı: kullanıcının yazdığı metin e-postada kod olarak çalışmasın */
    private function h(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}
