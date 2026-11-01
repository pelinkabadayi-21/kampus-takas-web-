<?php
class TradeOfferRepository extends BaseRepository
{
    /** Teklif + ilan + taraflar: kontrol ve listeleme için ortak SELECT */
    private const SELECT = '
        SELECT o.*, l.title AS listing_title, l.user_id AS listing_owner_id, l.status AS listing_status,
               l.listing_type, l.price AS listing_price, l.meeting_point,
               owner.full_name AS owner_name, offerer.full_name AS offerer_name,
               ol.title AS offered_listing_title, ol.status AS offered_listing_status,
               (SELECT image_url FROM listing_images i WHERE i.listing_id = l.id ORDER BY sort_order, id LIMIT 1) AS cover_image
        FROM trade_offers o
        JOIN listings l        ON l.id = o.listing_id
        JOIN users owner       ON owner.id = l.user_id
        JOIN users offerer     ON offerer.id = o.offerer_id
        LEFT JOIN listings ol  ON ol.id = o.offered_listing_id';

    public function create(array $d): int
    {
        $row = array_intersect_key($d, array_flip(['listing_id', 'offerer_id', 'offered_listing_id', 'offered_amount', 'note', 'created_at']));
        $row['created_at'] ??= date('Y-m-d H:i:s');      // zaman PHP'den: sunucu saat dilimi farkı olmasın
        return $this->insert('trade_offers', $row);
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne(self::SELECT . ' WHERE o.id = ?', [$id]);
    }

    public function forListing(int $listingId, ?int $onlyOffererId = null): array
    {
        $sql = self::SELECT . ' WHERE o.listing_id = ?'; $p = [$listingId];
        if ($onlyOffererId) { $sql .= ' AND o.offerer_id = ?'; $p[] = $onlyOffererId; }
        return $this->fetchAll($sql . ' ORDER BY o.id DESC', $p);
    }

    /** Kullanıcının ilanlarına gelen teklifler */
    public function received(int $userId): array
    {
        return $this->fetchAll(self::SELECT . ' WHERE l.user_id = ? ORDER BY o.id DESC LIMIT 100', [$userId]);
    }

    /** Kullanıcının verdiği teklifler */
    public function sent(int $userId): array
    {
        return $this->fetchAll(self::SELECT . ' WHERE o.offerer_id = ? ORDER BY o.id DESC LIMIT 100', [$userId]);
    }

    public function pendingExists(int $listingId, int $offererId): bool
    {
        return (bool) $this->fetchOne(
            "SELECT 1 AS x FROM trade_offers WHERE listing_id = ? AND offerer_id = ? AND status = 'beklemede'", [$listingId, $offererId]);
    }

    public function countPendingReceived(int $userId): int
    {
        return (int) $this->fetchOne(
            "SELECT COUNT(*) AS n FROM trade_offers o JOIN listings l ON l.id = o.listing_id
             WHERE l.user_id = ? AND o.status = 'beklemede'", [$userId])['n'];
    }

    /**
     * Verilen ilanlara (hedef olarak ya da karşılığında önerilen olarak) bağlı bekleyen teklifler.
     * İlan rezerve olunca bu teklifler artık geçersizdir.
     */
    public function pendingInvolving(array $listingIds, int $exceptOfferId): array
    {
        if (!$listingIds) return [];
        $in = implode(',', array_fill(0, count($listingIds), '?'));
        return $this->fetchAll(self::SELECT . " WHERE o.status = 'beklemede' AND o.id <> ?
            AND (o.listing_id IN ($in) OR o.offered_listing_id IN ($in))",
            array_merge([$exceptOfferId], $listingIds, $listingIds));
    }

    public function setStatus(int $id, string $status, ?string $respondedAt = null): int
    {
        return $this->execute('UPDATE trade_offers SET status = ?, responded_at = ? WHERE id = ?', [$status, $respondedAt, $id]);
    }

    /** Geriye uyumluluk (Hafta 4 testi): kabul edilen dışındaki bekleyen teklifleri reddet. */
    public function rejectOthers(int $listingId, int $exceptOfferId): int
    {
        return $this->execute(
            "UPDATE trade_offers SET status = 'red' WHERE listing_id = ? AND id <> ? AND status = 'beklemede'",
            [$listingId, $exceptOfferId]);
    }

    public function delete(int $id): int
    {
        return $this->execute('DELETE FROM trade_offers WHERE id = ?', [$id]);
    }
}
