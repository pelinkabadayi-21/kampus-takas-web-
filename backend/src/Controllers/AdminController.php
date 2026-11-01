<?php
/** SUNUM KATMANI - Sadece "yonetici" rolünün erişebildiği uçlar */
class AdminController
{
    public function __construct(private UserRepository $users) {}

    // GET /api/admin/users
    public function users(): void
    {
        Auth::requireRole('yonetici');
        Response::json(['success' => true, 'data' => $this->users->all()]);
    }

    // PUT /api/admin/users/{id}/role   {"role": "yonetici" | "ogrenci"}
    public function setRole(int $id): void
    {
        $me = Auth::requireRole('yonetici');
        $role = Request::json()['role'] ?? '';
        if (!in_array($role, ['ogrenci', 'yonetici'], true)) throw new ValidationException('Geçersiz rol');
        if ($id === (int) $me['id']) throw new ValidationException('Kendi rolünüzü değiştiremezsiniz');
        if (!$this->users->findById($id)) throw new ValidationException('Kullanıcı bulunamadı', [], 404);
        $this->users->setRole($id, $role);
        Response::json(['success' => true, 'data' => $this->users->findById($id)]);
    }

    // PUT /api/admin/users/{id}/verify   {"is_verified": true|false}
    public function setVerified(int $id): void
    {
        Auth::requireRole('yonetici');
        if (!$this->users->findById($id)) throw new ValidationException('Kullanıcı bulunamadı', [], 404);
        $this->users->update($id, ['is_verified' => !empty(Request::json()['is_verified']) ? 1 : 0]);
        Response::json(['success' => true, 'data' => $this->users->findById($id)]);
    }
}
