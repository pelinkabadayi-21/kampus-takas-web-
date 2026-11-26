<?php
/** SUNUM KATMANI - Sadece "yonetici" rolünün erişebildiği uçlar */
class AdminController
{
    /** Kullanıcı verileri; Hafta 9'dan itibaren harici servis günlüğü ve ayarları da alınır. */
    public function __construct(
        private UserRepository $users,
        private ?ApiLogRepository $logs = null,
        private ?Integrations $integrations = null
    ) {}

    // GET /api/admin/integrations   (Hafta 9) — harici servislerin durumu, son 24 saat özeti, son çağrılar
    public function integrations(): void
    {
        Auth::requireRole('yonetici');
        Response::json(['success' => true, 'data' => [
            'status'  => $this->integrations->status(),        // anahtarların kendisi ASLA gönderilmez
            'keys'    => $this->integrations->keyFingerprints(), // sadece uzunluk ve son 4 karakter (Hafta 10)
            'summary' => $this->logs->summary(24),
            'recent'  => $this->logs->recent(20),
        ]]);
    }

    // POST /api/admin/integrations/test   {"service": "email" | "image"}   (Hafta 9)
    // Yöneticinin, anahtarların doğru girildiğini canlı sitede denemesi için.
    public function testIntegration(): void
    {
        $me = Auth::requireRole('yonetici');
        $service = Request::json()['service'] ?? '';
        $start = microtime(true);
        try {
            if ($service === 'email') {
                $mailer = $this->integrations->mailer() ?? throw new ValidationException('E-posta servisi ayarlı değil (BREVO_API_KEY ve MAIL_FROM)');
                // Test e-postası yöneticinin kendi adresine gider
                $mailer->send($me['email'], $me['full_name'], 'Kampüs Takas – test e-postası',
                    '<p>Bu bir test e-postasıdır. Brevo entegrasyonu çalışıyor ✔</p>', (int) $me['id']);
                $msg = 'Test e-postası ' . $me['email'] . ' adresine gönderildi';
            } elseif ($service === 'image') {
                $storage = $this->integrations->imageStorage() ?? throw new ValidationException('Görsel servisi ayarlı değil (CLOUDINARY_URL)');
                if (!extension_loaded('gd')) throw new ValidationException('Test görseli için GD eklentisi gerekli');
                // 64x64 mor bir test görseli üret, yükle, sonra hemen sil
                $tmp = tempnam(sys_get_temp_dir(), 'kt_test_');
                $img = imagecreatetruecolor(64, 64);
                imagefill($img, 0, 0, imagecolorallocate($img, 91, 63, 209));
                imagejpeg($img, $tmp, 80); imagedestroy($img);
                try {
                    $url = $storage->put($tmp, bin2hex(random_bytes(16)), 'jpg', (int) $me['id']);
                    $storage->delete($url, (int) $me['id']);
                } finally { unlink($tmp); }
                $msg = 'Test görseli Cloudinary\'ye yüklendi ve silindi';
            } else {
                throw new ValidationException('Geçersiz servis (email veya image)');
            }
        } catch (ExternalApiException $e) {
            // Servis hatası: 502 (bizim sunucumuz değil, karşı servis hata verdi)
            Response::json(['success' => false, 'error' => $e->getMessage(), 'attempts' => $e->attempts], 502);
        }
        Response::json(['success' => true, 'message' => $msg, 'duration_ms' => (int) round((microtime(true) - $start) * 1000)]);
    }

    // GET /api/admin/users
    public function users(): void
    {
        Auth::requireRole('yonetici');
        // Sadece yönetici girebilir; tüm kullanıcıları JSON olarak döndür
        Response::json(['success' => true, 'data' => $this->users->all()]);
    }

    // PUT /api/admin/users/{id}/role   {"role": "yonetici" | "ogrenci"}
    public function setRole(int $id): void
    {
        // Giriş yapan yönetici
        $me = Auth::requireRole('yonetici');
        // İstekten yeni rolü al; sadece ogrenci/yonetici olabilir
        $role = Request::json()['role'] ?? '';
        if (!in_array($role, ['ogrenci', 'yonetici'], true)) throw new ValidationException('Geçersiz rol');
        // Yönetici kendi rolünü düşüremez (sistemde yönetici kalmayabilir)
        if ($id === (int) $me['id']) throw new ValidationException('Kendi rolünüzü değiştiremezsiniz');
        if (!$this->users->findById($id)) throw new ValidationException('Kullanıcı bulunamadı', [], 404);
        // Rolü güncelle ve güncel kullanıcıyı geri döndür
        $this->users->setRole($id, $role);
        Response::json(['success' => true, 'data' => $this->users->findById($id)]);
    }

    // GET /api/admin/stats   ÖĞRENCİ BÖLÜMÜ (Hafta 5): rol kontrolünün uygulandığı uç
    // Sadece yönetici görebilir; giriş yoksa 401, öğrenciyse 403 döner.
    public function stats(): void
    {
        Auth::requireRole('yonetici');
        // Yönetim paneli istatistikleri
        Response::json(['success' => true, 'data' => $this->users->stats()]);
    }

    // PUT /api/admin/users/{id}/verify   {"is_verified": true|false}
    public function setVerified(int $id): void
    {
        Auth::requireRole('yonetici');
        if (!$this->users->findById($id)) throw new ValidationException('Kullanıcı bulunamadı', [], 404);
        // Doğrulanmış öğrenci işaretini aç/kapat
        $this->users->update($id, ['is_verified' => !empty(Request::json()['is_verified']) ? 1 : 0]);
        Response::json(['success' => true, 'data' => $this->users->findById($id)]);
    }
}
