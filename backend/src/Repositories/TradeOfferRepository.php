<?php
class TradeOfferRepository extends BaseRepository
{
    /** Teklif + ilan + taraflar: kontrol ve listeleme için ortak SELECT */
    // Teklifle birlikte ilanın başlığı/durumu, sahibinin ve teklif verenin adı, karşılığında önerilen ilan ve kapak fotoğrafı da gelsin diye tabloları birleştiren ortak sorgu
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

    /** Yeni takas teklifi kaydeder. */
    public function create(array $d): int
    {
        // Sadece teklif tablosuna ait alanları al
        $row = array_intersect_key($d, array_flip(['listing_id', 'offerer_id', 'offered_listing_id', 'offered_amount', 'note', 'created_at']));
        $row['created_at'] ??= date('Y-m-d H:i:s');      // zaman PHP'den: sunucu saat dilimi farkı olmasın
        return $this->insert('trade_offers', $row);
    }

    /** id'ye göre teklifi, ilan ve taraf bilgileriyle getirir. */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(self::SELECT . ' WHERE o.id = ?', [$id]);
    }

    /** Bir ilana gelen teklifler (istenirse sadece bir kişinin teklifleri). */
    public function forListing(int $listingId, ?int $onlyOffererId = null): array
    {
        // Bu ilana gelen teklifler
        $sql = self::SELECT . ' WHERE o.listing_id = ?'; $p = [$listingId];
        // Sadece belirli kişinin teklifleri istendiyse koşul ekle
        if ($onlyOffererId) { $sql .= ' AND o.offerer_id = ?'; $p[] = $onlyOffererId; }
        return $this->fetchAll($sql . ' ORDER BY o.id DESC', $p);
    }

    /** Kullanıcının ilanlarına gelen teklifler */
    public function received(int $userId): array
    {
        // İlan sahibi bu kullanıcı olan teklifler = bana gelenler
        return $this->fetchAll(self::SELECT . ' WHERE l.user_id = ? ORDER BY o.id DESC LIMIT 100', [$userId]);
    }

    /** Kullanıcının verdiği teklifler */
    public function sent(int $userId): array
    {
        // Teklifi veren bu kullanıcı = benim gönderdiklerim
        return $this->fetchAll(self::SELECT . ' WHERE o.offerer_id = ? ORDER BY o.id DESC LIMIT 100', [$userId]);
    }

    /** Bu kişinin bu ilana bekleyen bir teklifi var mı? */
    public function pendingExists(int $listingId, int $offererId): bool
    {
        return (bool) $this->fetchOne(
            "SELECT 1 AS x FROM trade_offers WHERE listing_id = ? AND offerer_id = ? AND status = 'beklemede'", [$listingId, $offererId]);
    }

    /** Kullanıcının ilanlarına gelen bekleyen teklif sayısı. */
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
        // İlan listesi boşsa arama yapma
        if (!$listingIds) return [];
        // İlan sayısı kadar ? üret: "?,?" (IN (...) içinde kullanılacak)
        $in = implode(',', array_fill(0, count($listingIds), '?'));
        // Kabul edilen teklif hariç, bu ilanlardan birini isteyen VEYA karşılığında öneren bekleyen teklifler
        return $this->fetchAll(self::SELECT . " WHERE o.status = 'beklemede' AND o.id <> ?
            AND (o.listing_id IN ($in) OR o.offered_listing_id IN ($in))",
            array_merge([$exceptOfferId], $listingIds, $listingIds));
    }

    /** Teklifin durumunu ve yanıt zamanını değiştirir. */
    public function setStatus(int $id, string $status, ?string $respondedAt = null): int
    {
        // Durumu ve ne zaman yanıtlandığını kaydet
        return $this->execute('UPDATE trade_offers SET status = ?, responded_at = ? WHERE id = ?', [$status, $respondedAt, $id]);
    }

    /** Geriye uyumluluk (Hafta 4 testi): kabul edilen dışındaki bekleyen teklifleri reddet. */
    public function rejectOthers(int $listingId, int $exceptOfferId): int
    {
        return $this->execute(
            "UPDATE trade_offers SET status = 'red' WHERE listing_id = ? AND id <> ? AND status = 'beklemede'",
            [$listingId, $exceptOfferId]);
    }

    /** Teklifi siler. */
    public function delete(int $id): int
    {
        return $this->execute('DELETE FROM trade_offers WHERE id = ?', [$id]);
    }
}
