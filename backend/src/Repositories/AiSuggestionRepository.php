<?php
/**
 * VERİ KATMANI - Yapay zekâ öneri günlüğü ve fiyat önerisinin dayandığı site verisi (Hafta 10)
 */
class AiSuggestionRepository extends BaseRepository
{
    private const COLS = ['user_id', 'source', 'model', 'prompt_version', 'input_title', 'input_summary',
        'item_condition', 'listing_type', 'had_image', 'raw_output', 'category_slug', 'price_min', 'price',
        'price_max', 'confidence', 'reason', 'warnings', 'error', 'duration_ms'];

    /** Bir öneri isteğini kaydeder ve id'sini döndürür. */
    public function log(array $d): int
    {
        $row = array_intersect_key($d, array_flip(self::COLS));
        if (isset($row['raw_output'])) $row['raw_output'] = mb_substr((string) $row['raw_output'], 0, 4000);
        if (isset($row['error']))      $row['error']      = mb_substr((string) $row['error'], 0, 255);
        if (isset($row['reason']))     $row['reason']     = mb_substr((string) $row['reason'], 0, 300);
        if (isset($row['warnings']) && is_array($row['warnings'])) {
            $row['warnings'] = mb_substr(json_encode($row['warnings'], JSON_UNESCAPED_UNICODE), 0, 1000);
        }
        $row['created_at'] = date('Y-m-d H:i:s');
        return $this->insert('ai_suggestions', $row);
    }

    /** id'ye göre tek öneri (yoksa null). */
    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM ai_suggestions WHERE id = ?', [$id]);
    }

    /** Öneri bir ilan açılırken kullanıldı: hangi ilan ve kullanıcı öneriyi kabul etti mi. */
    public function markUsed(int $id, int $listingId, bool $acceptedCategory, ?bool $acceptedPrice): void
    {
        $this->execute('UPDATE ai_suggestions SET listing_id = ?, accepted_category = ?, accepted_price = ? WHERE id = ?',
            [$listingId, (int) $acceptedCategory, $acceptedPrice === null ? null : (int) $acceptedPrice, $id]);
    }

    /** A6: Kullanıcının son N dakikadaki öneri sayısı. */
    public function countRecentByUser(int $userId, int $minutes): int
    {
        $r = $this->fetchOne('SELECT COUNT(*) AS n FROM ai_suggestions WHERE user_id = ? AND created_at >= ?',
            [$userId, date('Y-m-d H:i:s', time() - $minutes * 60)]);
        return (int) ($r['n'] ?? 0);
    }

    /** Yönetim paneli: son öneriler (ham çıktı hariç). */
    public function recent(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return $this->fetchAll("SELECT id, user_id, listing_id, source, model, prompt_version, input_title, item_condition,
                listing_type, had_image, category_slug, price_min, price, price_max, confidence, reason, warnings, error,
                accepted_category, accepted_price, duration_ms, created_at
            FROM ai_suggestions ORDER BY id DESC LIMIT $limit");
    }

    /** Yönetim paneli: son N günün özeti — kaç öneri, kaçı yapay zekâdan, kaç düzeltme, kabul oranları. */
    public function stats(int $days = 7): array
    {
        $r = $this->fetchOne("SELECT COUNT(*) AS total,
                SUM(CASE WHEN source = 'ai' THEN 1 ELSE 0 END) AS from_ai,
                SUM(CASE WHEN source = 'kural' THEN 1 ELSE 0 END) AS from_rules,
                SUM(CASE WHEN error IS NOT NULL THEN 1 ELSE 0 END) AS errors,
                SUM(CASE WHEN warnings IS NOT NULL AND warnings <> '[]' THEN 1 ELSE 0 END) AS corrected,
                SUM(CASE WHEN listing_id IS NOT NULL THEN 1 ELSE 0 END) AS used,
                SUM(CASE WHEN accepted_category = 1 THEN 1 ELSE 0 END) AS accepted_category,
                SUM(CASE WHEN accepted_price = 1 THEN 1 ELSE 0 END) AS accepted_price,
                ROUND(AVG(duration_ms)) AS avg_ms
            FROM ai_suggestions WHERE created_at >= ?", [date('Y-m-d H:i:s', time() - $days * 86400)]);
        return array_map(fn($v) => (int) $v, $r ?? []);
    }

    /**
     * Fiyat önerisinin dayandığı site verisi: satılık (aktif veya tamamlanmış) ilanların
     * kategori, başlık, fiyat ve durumu. Medyan ve benzer ilanlar servis tarafında hesaplanır.
     */
    public function marketRows(int $limit = 500): array
    {
        return $this->fetchAll("SELECT c.slug, l.title, l.price, l.item_condition
            FROM listings l JOIN categories c ON c.id = l.category_id
            WHERE l.listing_type = 'satis' AND l.price IS NOT NULL AND l.price > 0
              AND l.status IN ('aktif', 'rezerve', 'tamamlandi')
            ORDER BY l.id DESC LIMIT " . max(1, min(2000, $limit)));
    }
}
