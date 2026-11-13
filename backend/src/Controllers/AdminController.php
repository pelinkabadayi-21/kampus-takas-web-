<?php
/** SUNUM KATMANI - Sadece "yonetici" rolünün erişebildiği uçlar */
class AdminController
{
    /** Kullanıcı verilerine erişmek için UserRepository alınır. */
    public function __construct(private UserRepository $users) {}

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
