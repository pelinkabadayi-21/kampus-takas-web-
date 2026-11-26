<?php
/**
 * VERİ KATMANI - Harici API çağrılarının günlüğü (Hafta 9)
 *
 * Her Cloudinary / Brevo çağrısının sonucu burada tutulur: hangi servis, hangi işlem, HTTP durum
 * kodu, kaç deneme, ne kadar sürdü, hata neydi. API anahtarı ve e-posta içeriği YAZILMAZ.
 * Yönetim panelinde "Harici servisler" kartı ve e-posta saatlik sınırı (E5) bu tabloyu kullanır.
 */
class ApiLogRepository extends BaseRepository
{
    /** Bir çağrının sonucunu kaydeder. */
    public function log(array $d): int
    {
        return $this->insert('external_api_logs', [
            'service'     => mb_substr((string) $d['service'], 0, 30),
            'action'      => mb_substr((string) ($d['action'] ?? 'istek'), 0, 30),
            'user_id'     => $d['user_id'] ?? null,
            'status_code' => $d['status_code'] ?? null,
            'success'     => (int) ($d['success'] ?? 0),
            'attempts'    => (int) ($d['attempts'] ?? 1),
            'duration_ms' => (int) ($d['duration_ms'] ?? 0),
            'error'       => isset($d['error']) ? mb_substr((string) $d['error'], 0, 255) : null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /** Son çağrılar (yönetim paneli için, en yeni en üstte). */
    public function recent(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return $this->fetchAll("SELECT id, service, action, user_id, status_code, success, attempts, duration_ms, error, created_at
            FROM external_api_logs ORDER BY id DESC LIMIT $limit");
    }

    /** Son 24 saatin servis bazında özeti: toplam, başarılı, başarısız, ortalama süre. */
    public function summary(int $hours = 24): array
    {
        return $this->fetchAll('SELECT service,
                COUNT(*) AS total,
                SUM(success) AS ok,
                COUNT(*) - SUM(success) AS failed,
                ROUND(AVG(duration_ms)) AS avg_ms
            FROM external_api_logs WHERE created_at >= ? GROUP BY service ORDER BY service',
            [date('Y-m-d H:i:s', time() - $hours * 3600)]);
    }

    /** Bir kullanıcı için son N dakikada BAŞARIYLA yapılmış belirli işlem sayısı (E5: saatlik e-posta sınırı). */
    public function countRecent(string $action, int $userId, int $minutes): int
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS n FROM external_api_logs
            WHERE action = ? AND user_id = ? AND success = 1 AND created_at >= ?',
            [$action, $userId, date('Y-m-d H:i:s', time() - $minutes * 60)]);
        return (int) ($row['n'] ?? 0);
    }
}
