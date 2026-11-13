<?php
/** VERİ KATMANI - Değerlendirme (puan + yorum) tablosunun SQL sorguları. */
class ReviewRepository extends BaseRepository
{
    /** Yeni değerlendirme kaydeder. */
    public function create(array $d): int
    {
        // Sadece izin verilen alanları al, tarihi ekle
        $row = array_intersect_key($d, array_flip(['trade_offer_id', 'reviewer_id', 'reviewed_id', 'rating', 'comment']));
        $row['created_at'] = date('Y-m-d H:i:s');
        return $this->insert('reviews', $row);
    }

    /** Bu kişi bu takası daha önce değerlendirmiş mi? */
    public function exists(int $offerId, int $reviewerId): bool
    {
        return (bool) $this->fetchOne('SELECT 1 AS x FROM reviews WHERE trade_offer_id = ? AND reviewer_id = ?', [$offerId, $reviewerId]);
    }

    /** Kullanıcının değerlendirdiği teklif id'leri (arayüzde "Değerlendir" butonunu gizlemek için) */
    public function reviewedOfferIds(int $reviewerId): array
    {
        return array_map('intval', array_column($this->fetchAll('SELECT trade_offer_id FROM reviews WHERE reviewer_id = ?', [$reviewerId]), 'trade_offer_id'));
    }

    /** Bir kullanıcının aldığı değerlendirmeleri, yazanın adıyla getirir. */
    public function forUser(int $userId): array
    {
        return $this->fetchAll(
            'SELECT r.rating, r.comment, r.created_at, u.full_name AS reviewer_name
             FROM reviews r JOIN users u ON u.id = r.reviewer_id
             WHERE r.reviewed_id = ? ORDER BY r.created_at DESC', [$userId]);
    }

    /** Kullanıcının ortalama puanı ve yorum sayısı */
    public function summary(int $userId): array
    {
        // Ortalama puan (1 ondalık) ve toplam değerlendirme sayısı
        $r = $this->fetchOne('SELECT ROUND(AVG(rating), 1) AS average, COUNT(*) AS total FROM reviews WHERE reviewed_id = ?', [$userId]);
        return ['average' => $r['average'] !== null ? (float) $r['average'] : null, 'total' => (int) $r['total']];
    }

    /** Değerlendirmeyi siler. */
    public function delete(int $id): int
    {
        return $this->execute('DELETE FROM reviews WHERE id = ?', [$id]);
    }
}
